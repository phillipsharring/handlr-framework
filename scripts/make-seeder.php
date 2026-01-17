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

class MakeSeederCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:seeder')
            ->setDescription('Create a new seeder file.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the seeder (e.g., "Series", "users", "UserPacks")');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = trim($input->getArgument('name'));

        // Normalize: "series" -> "Series", "user_packs" -> "UserPacks"
        $studly = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', strtolower($name))));

        // Filename is lowercase with underscores
        $filename = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $studly)) . '.php';
        $path = getcwd() . '/seeds/' . $filename;

        $filesystem = new Filesystem();
        $filesystem->mkdir(dirname($path));

        $stub = <<<'PHP'
<?php

declare(strict_types=1);

// TODO: Update these use statements to match your Table classes
// use App\YourDomain\{StudlyName}Table;

return [
    // {StudlyName}Table::class => [
    //     [
    //         // 'field' => 'value',
    //         '_relations' => [
    //             // RelatedTable::class => [
    //             //     ['field' => 'value'],
    //             // ],
    //         ],
    //     ],
    // ],
];
PHP;

        // Replace placeholder with actual name
        $stub = str_replace('{StudlyName}', $studly, $stub);

        file_put_contents($path, $stub);
        $output->writeln("<info>Created seeder: seeds/{$filename}</info>");

        return Command::SUCCESS;
    }
}

$app = new Application();
$app->addCommand(new MakeSeederCommand());
$app->setDefaultCommand('make:seeder', true);
$app->run();