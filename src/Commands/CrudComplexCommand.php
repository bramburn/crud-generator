<?php

namespace Appzcoder\CrudGenerator\Commands;

use File;
use Illuminate\Console\Command;

class CrudComplexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crud:complex
                            {name : The name of the Crud.}
                            {--complexjson= : Create Complex CRUD with multiple relationship easily.}
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate CRUD from complex JSon file';

    /** @var string  */
    protected $FinalRouteName = '';

    /** @var string  */
    protected $controller = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $this->info('test run');
        try
        {
            $json = File::get($fullFilePath);
        } catch (Illuminate\Filesystem\FileNotFoundException $exception) {
            $this->error("File does not exists");
        }
    }

}
