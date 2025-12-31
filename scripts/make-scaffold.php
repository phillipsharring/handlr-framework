#!/usr/bin/env php
<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

require_once __DIR__ . '/../vendor/autoload.php';

$app = new Application();

$app->register('make:scaffold')
    ->addArgument('name', InputArgument::REQUIRED, 'Feature name (e.g. GamePlay/CreateSeries)')
    ->addOption('no-pipe', null, InputOption::VALUE_NONE, 'Skip Pipe generation')
    ->addOption('event-only', null, InputOption::VALUE_NONE, 'Only generate Input and Handler for domain events')
    ->setDescription('Scaffold a new feature with Input, Handler, Pipe, and Test')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $name = $input->getArgument('name');
        $noPipe = $input->getOption('no-pipe');
        $eventOnly = $input->getOption('event-only');

        $fs = new Filesystem();

        $baseDir = getcwd() . '/app/' . $name;
        $className = basename(str_replace('\\', '/', $name));
        $namespace = 'App\\' . str_replace('/', '\\', $name);

        $output->writeln("Scaffolding <info>$name</info>...");

        if ($fs->exists($baseDir)) {
            $output->writeln("<error>Directory already exists: $baseDir</error>");
            return;
        }

        $fs->mkdir($baseDir);

        $stub = fn($type) => match($type) {
            'Input' => "<?php

namespace $namespace;

use Handlr\\Handlers\\HandlerInput;

class {$className}Input implements HandlerInput
{
    public function __construct(
        array \$body
    ) {
        // TODO: Extract and sanitize values from \$body
    }
}",
            'Handler' => "<?php

namespace $namespace;

use Handlr\\Handlers\\Handler;
use Handlr\\Handlers\\HandlerInput;
use Handlr\\Handlers\\HandlerResult;

class {$className}Handler implements Handler
{
    public function handle(array|HandlerInput \$input): ?HandlerResult
    {
        // TODO: Implement logic
        return HandlerResult::ok(['message' => '{$className} executed']);
    }
}",
            'Pipe' => "<?php

namespace $namespace;

use Handlr\\Pipes\\Pipe;
use Handlr\\Request;
use Handlr\\Response;

class {$className}Pipe implements Pipe
{
    public function handle(Request \$request): Response
    {
        \$input = \$request->asInput({$className}Input::class);
        \$handler = new {$className}Handler();
        \$result = \$handler->handle(\$input);

        return \$result?->toResponse() ?? Response::json(['status' => 'no result']);
    }
}",
            'Test' => "<?php

declare(strict_types=1);

use PHPUnit\\Framework\\TestCase;

class {$className}Test extends TestCase
{
    public function testHandlerReturnsExpectedResult(): void
    {
        \$handler = new App\\$name\\{$className}Handler();
        \$input = new App\\$name\\{$className}Input([
            // TODO: Fill with test input
        ]);

        \$result = \$handler->handle(\$input);

        \$this->assertNotNull(\$result);
        \$this->assertTrue(\$result->ok);
    }
}"
        };

        $fs->dumpFile("$baseDir/{$className}Input.php", $stub('Input'));
        $fs->dumpFile("$baseDir/{$className}Handler.php", $stub('Handler'));

        if (!$eventOnly && !$noPipe) {
            $fs->dumpFile("$baseDir/{$className}Pipe.php", $stub('Pipe'));
        }

        if (!$eventOnly) {
            $fs->dumpFile("$baseDir/{$className}Test.php", $stub('Test'));
        }

        $output->writeln('<info>Scaffold complete.</info>');
    });

$app->run();
