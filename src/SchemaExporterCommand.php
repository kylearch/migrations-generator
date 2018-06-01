<?php

namespace KyleArch\SchemaExporter;

use Way\Generators\Commands\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

use Way\Generators\Generator;
use Way\Generators\Filesystem\Filesystem;
use Way\Generators\Compilers\TemplateCompiler;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Support\Facades\DB;

use KyleArch\SchemaExporter\Generators\SchemaGenerator;
use KyleArch\SchemaExporter\Syntax\AddToTable;
use KyleArch\SchemaExporter\Syntax\DroppedTable;

use Illuminate\Contracts\Config\Repository as Config;

class SchemaExporterCommand extends GeneratorCommand
{

    /**
     * The console command name.
     * @var string
     */
    protected $name = 'export:schema';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Generate a single migration from an existing db schema.';

    /**
     * @var \Way\Generators\Filesystem\Filesystem
     */
    protected $file;

    /**
     * @var \Way\Generators\Compilers\TemplateCompiler
     */
    protected $compiler;

    /**
     * @var \Illuminate\Database\Migrations\MigrationRepositoryInterface $repository
     */
    protected $repository;

    /**
     * @var \Illuminate\Config\Repository $config
     */
    protected $config;

    /**
     * @var \KyleArch\SchemaExporter\Generators\SchemaGenerator
     */
    protected $schemaGenerator;

    /**
     * Array of Fields to create in a new Migration
     * Namely: Columns, Indexes and Foreign Keys
     * @var array
     */
    protected $fields = [];

    /**
     * List of Migrations that has been done
     * @var array
     */
    protected $migrations = [];

    /**
     * @var bool
     */
    protected $log = false;

    /**
     * @var int
     */
    protected $batch;

    /**
     * Filename date prefix (Y_m_d_His)
     * @var string
     */
    protected $datePrefix;

    /**
     * @var string
     */
    protected $migrationName;

    /**
     * @var string
     */
    protected $method;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var string|null
     */
    protected $connection = null;

    /**
     * @var bool
     */
    private $ignoreTmpTables = true;

    /**
     * @var bool
     */
    private $ignoreMigrationsTable = true;

    /**
     * @var array
     */
    private $ups = [];

    /**
     * @var array
     */
    private $downs = [];

    /**
     * @param \Way\Generators\Generator                                    $generator
     * @param \Way\Generators\Filesystem\Filesystem                        $file
     * @param \Way\Generators\Compilers\TemplateCompiler                   $compiler
     * @param \Illuminate\Database\Migrations\MigrationRepositoryInterface $repository
     * @param \Illuminate\Contracts\Config\Repository                      $config
     */
    public function __construct(Generator $generator, Filesystem $file, TemplateCompiler $compiler, MigrationRepositoryInterface $repository, Config $config)
    {
        $this->file       = $file;
        $this->compiler   = $compiler;
        $this->repository = $repository;
        $this->config     = $config;

        parent::__construct($generator);
    }

    /**
     * Execute the console command. Added for Laravel 5.5
     * @return void
     */
    public function handle()
    {
        $this->ignoreTmpTables       = (bool)$this->option('ignoreTmp');
        $this->ignoreMigrationsTable = (bool)$this->option('ignoreMigrations');

        $this->fire();
    }

    /**
     * Execute the console command.
     * @return void
     */
    public function fire()
    {
        if ($this->option('connection') !== $this->config->get('database.default')) {
            $this->connection = $this->option('connection');
        }

        $this->migrationName   = 'create_' . snake_case(DB::getDatabaseName()) . '_database';
        $this->schemaGenerator = new SchemaGenerator($this->option('connection'), $this->option('defaultIndexNames'), $this->option('defaultFKNames'));

        $tables = $this->getTablesArray();

        $this->table(['Table Name', 'Included'], $tables);

        if ($this->log) {
            $this->repository->setSource($this->option('connection'));
            if (!$this->repository->repositoryExists()) {
                $options = ['--database' => $this->option('connection')];
                $this->call('migrate:install', $options);
            }
            $batch       = $this->repository->getNextBatchNumber();
            $this->batch = $this->askNumeric('Next Batch Number is: ' . $batch . '. We recommend using Batch Number 0 so that it becomes the "first" migration', 0);
        }

        $this->info("Setting up Tables and Index Migrations");
        $this->datePrefix = date('Y_m_d_His');
        $this->generateUpsAndDowns($tables);

        // Ignoring since we don't enforce Foreign Keys
        // $this->info("\nSetting up Foreign Key Migrations\n");
        // $this->datePrefix = date('Y_m_d_His', strtotime('+1 second'));
        // $this->generateForeignKeys($tables);

        $this->generate();

        $this->info("Finished!");
    }

    /**
     * Ask user for a Numeric Value, or blank for default
     *
     * @param  string    $question Question to ask
     * @param  int|float $default  Default Value (optional)
     *
     * @return int|float           Answer
     */
    protected function askNumeric($question, $default = null)
    {
        $ask = 'Your answer needs to be a numeric value';
        if (!is_null($default)) {
            $question .= ' [Default: ' . $default . '] ';
            $ask      .= ' or blank for default';
        }
        $answer = $this->ask($question);
        while (!is_numeric($answer) and !($answer == '' and !is_null($default))) {
            $answer = $this->ask($ask . '. ');
        }
        if ($answer == '') {
            $answer = $default;
        }

        return $answer;
    }

    /**
     * @return void
     */
    private function formatFile()
    {
        $this->info("\nFormatting file...");
        $file    = $this->getFileGenerationPath();
        $content = file_get_contents($file);

        $content = preg_replace('/\t/', '    ', $content);
        $content = preg_replace('/Schema::create/', '        Schema::create', $content);

        file_put_contents($file, $content);
    }

    /**
     * @param array $tables
     */
    protected function generateUpsAndDowns(array $tables)
    {
        $this->method = 'create';

        $tables = array_filter($tables, function ($table) {
            return $table['include'] !== false;
        });

        $progressBar = $this->output->createProgressBar(count($tables));

        foreach ($tables as $table) {
            $tableName = $table['name'];
            $fields    = $this->schemaGenerator->getFields($tableName);

            $this->ups[]   = (new AddToTable($this->file, $this->compiler))->run($fields, $tableName, $this->connection, 'create');
            $this->downs[] = (new DroppedTable)->drop($tableName, $this->connection, true);

            $progressBar->advance();
        }

        // Always drop tables in reverse order!
        rsort($this->downs);

        $progressBar->finish();
    }

    /**
     * Generate foreign key migrations.
     * Ignored until needed
     *
     * @param  array $tables List of tables to create migrations for
     *
     * @return void
     */
    /*
    protected function generateForeignKeys(array $tables)
    {
        $this->method = 'table';

        foreach ($tables as $table) {
            $this->table = $table;
            $this->migrationName = 'add_foreign_keys_to_' . $this->table . '_table';
            $this->fields = $this->schemaGenerator->getForeignKeyConstraints($this->table);
            $this->generate();
        }
    }
    */

    /**
     * Generate single Migration for the current database.
     * @return void
     */
    protected function generate()
    {
        if (!empty($this->ups)) {
            $this->generator->make($this->getTemplatePath(), $this->getTemplateData(), $this->getFileGenerationPath());
        }

        $this->formatFile();
    }

    /**
     * The path where the file will be created
     * @return string
     */
    protected function getFileGenerationPath()
    {
        $path          = $this->getPathByOptionOrConfig('path', 'migration_target_path');
        $migrationName = str_replace('/', '_', $this->migrationName);
        $fileName      = $this->getDatePrefix() . '_' . $migrationName . '.php';

        return "{$path}/{$fileName}";
    }

    /**
     * Get the date prefix for the migration.
     * @return string
     */
    protected function getDatePrefix()
    {
        return $this->datePrefix;
    }

    /**
     * Fetch an array of tables based on provided (assumed) include/exclude options
     * @return array
     */
    private function getTablesArray()
    {
        $tables = array_map('reset', DB::select("SHOW TABLES"));
        foreach ($tables as &$table) {
            $include = true;

            if ($this->ignoreTmpTables && strpos($table, 'tmp') === 0) {
                $include = false;
            }

            if ($this->ignoreMigrationsTable && $table === 'migrations') {
                $include = false;
            }

            $table = [
                'name'    => $table,
                'include' => $include ? '✓' : false,
            ];
        }

        return $tables;
    }

    /**
     * Fetch the template data
     * @return array
     */
    protected function getTemplateData()
    {
        return [
            'CLASS' => ucwords(camel_case($this->migrationName)),
            'UPS'   => implode("\n\n", $this->ups),
            'DOWNS' => implode("\n", $this->downs),
            'FKS'   => '',
        ];
    }

    /**
     * Get path to template for generator
     * @return string
     */
    protected function getTemplatePath()
    {
        return __DIR__ . "/templates/migration.txt";
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['connection', 'c', InputOption::VALUE_OPTIONAL, 'The database connection to use.', $this->config->get('database.default')],
            ['path', 'p', InputOption::VALUE_OPTIONAL, 'Where should the file be created?'],
            ['templatePath', 'tp', InputOption::VALUE_OPTIONAL, 'The location of the template for this generator'],
            ['defaultIndexNames', null, InputOption::VALUE_NONE, 'Don\'t use db index names for migrations'],
            ['defaultFKNames', null, InputOption::VALUE_NONE, 'Don\'t use db foreign key names for migrations'],
            ['ignoreTmp', null, InputOption::VALUE_OPTIONAL, 'Ignore tables prefixed with \'tmp\'', true],
            ['ignoreMigrations', null, InputOption::VALUE_OPTIONAL, 'Ignore an existing \'migrations\' table', true],
        ];
    }

}
