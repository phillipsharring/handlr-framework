<?php

declare(strict_types=1);

require_once __DIR__ . '/support/require-vendor-autoload.php';
requireVendorAutoload();

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class MakeMigrationCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:migration')
            ->setDescription('Create a new migration file.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the migration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = trim($input->getArgument('name'));
        $timestamp = date('YmdHis');
        $studly = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', strtolower($name))));
        $className = "Migration_{$timestamp}_{$studly}";

        $filename = "{$timestamp}_" . strtolower(preg_replace('/\s+/', '_', $name)) . ".php";
        $path = getcwd() . '/migrations/' . $filename;

        $filesystem = new Filesystem();
        $filesystem->mkdir(dirname($path));

        $stub = <<<PHP
<?php

use Handlr\Database\Migrations\BaseMigration;

class {$className} extends BaseMigration
{
    public function up(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE IF NOT EXISTS `` (
            ) ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_0900_ai_ci;
        SQL;
        $this->exec($sql);
    }

    public function down(): void
    {
        $this->exec('DROP TABLE IF EXISTS ``;');
    }
}

PHP;

        file_put_contents($path, $stub);
        $output->writeln("<info>✔ Created migration: migrations/{$filename}</info>");

        return Command::SUCCESS;
    }
}

$app = new Application();
$app->addCommand(new MakeMigrationCommand());
$app->setDefaultCommand('make:migration', true);
$app->run();
