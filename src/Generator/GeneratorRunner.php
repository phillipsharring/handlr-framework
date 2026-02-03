<?php

declare(strict_types=1);

namespace Handlr\Generator;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Orchestrates code generation from registered makers.
 *
 * Handles the Symfony Console boilerplate, filesystem operations, and output
 * formatting so that individual makers can focus on generating file content.
 *
 * ## Usage
 *
 * ```php
 * $runner = new GeneratorRunner('/path/to/stubs');
 * $runner->register(new MigrationMaker());
 * $runner->register(new ScaffoldMaker());
 * $runner->register(new SeederMaker());
 * $runner->run();
 * ```
 *
 * ## From command line
 *
 * ```bash
 * php make.php migration CreateUsers
 * php make.php scaffold GamePlay/CreateSeries --no-pipe
 * php make.php seeder UserPacks
 * ```
 */
class GeneratorRunner
{
    /** @var MakerInterface[] */
    private array $makers = [];

    private Filesystem $filesystem;

    /**
     * @param string $stubsPath Absolute path to the stubs directory
     */
    public function __construct(
        private string $stubsPath
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * Register a maker with the runner.
     *
     * @return $this For method chaining
     */
    public function register(MakerInterface $maker): self
    {
        $this->makers[$maker->name()] = $maker;
        return $this;
    }

    /**
     * Run the console application.
     */
    public function run(): void
    {
        $app = new Application('make', '1.0.0');

        foreach ($this->makers as $maker) {
            $app->add($this->buildCommand($maker));
        }

        try {
            $app->run();
        } catch (\Exception $e) {
            // Symfony Console handles most exceptions internally
        }
    }

    /**
     * Build a Symfony Console command from a maker.
     */
    private function buildCommand(MakerInterface $maker): Command
    {
        $command = new Command('make:' . $maker->name());
        $command->setDescription($maker->description());

        foreach ($maker->arguments() as $arg) {
            $command->addArgument(
                $arg[0],
                $arg[1],
                $arg[2],
                $arg[3] ?? null
            );
        }

        foreach ($maker->options() as $opt) {
            $command->addOption(
                $opt[0],
                $opt[1],
                $opt[2],
                $opt[3],
                $opt[4] ?? null
            );
        }

        $runner = $this;
        $command->setCode(function (InputInterface $input, OutputInterface $output) use ($maker, $runner) {
            return $runner->executeCommand($maker, $input, $output);
        });

        return $command;
    }

    /**
     * Execute a maker command.
     */
    public function executeCommand(MakerInterface $maker, InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('Creating <info>%s</info>...', $maker->name()));

        try {
            $files = $maker->generate($input, $this->stubsPath);
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Generation failed: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        if (empty($files)) {
            $output->writeln('<comment>No files to generate.</comment>');
            return Command::SUCCESS;
        }

        // Check for existing files that would be overwritten
        foreach ($files as $file) {
            if (!$file->allowOverwrite && $this->filesystem->exists($file->path)) {
                $output->writeln(sprintf('<error>File already exists: %s</error>', $file->path));
                return Command::FAILURE;
            }
        }

        // Write all files
        foreach ($files as $file) {
            $this->filesystem->mkdir(dirname($file->path));
            $this->filesystem->dumpFile($file->path, $file->content);

            $relativePath = $this->relativePath($file->path);
            $output->writeln(sprintf('  <info>✔</info> %s', $relativePath));
        }

        $output->writeln(sprintf('<info>Done. Created %d file(s).</info>', count($files)));
        return Command::SUCCESS;
    }

    /**
     * Convert absolute path to relative (from cwd) for cleaner output.
     */
    private function relativePath(string $absolutePath): string
    {
        $cwd = getcwd();
        if (str_starts_with($absolutePath, $cwd)) {
            return ltrim(substr($absolutePath, strlen($cwd)), '/');
        }
        return $absolutePath;
    }
}
