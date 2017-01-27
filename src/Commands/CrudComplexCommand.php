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
            $json = File::get('./complex.json');
        } catch (Illuminate\Filesystem\FileNotFoundException $exception) {
            $this->error("File does not exists");
        }

        $data = json_decode($json);

        foreach ($data as $entry) {
            // each entry is a CRUD

            foreach ($entry as $crud_entry) {
                // $this->CreateControllerFromObj($crud_entry); // only creates the Controller Commands
                // $this->CreateModelFromObj($crud_entry); // only creates the Model commands
                list($zero, $one) = $this->ProcessComplexJsonFields($crud_entry->data->fields);
                dd($one[0]);
            }

            // $this->info("Creating CRUD - ".$entry-)
        }

    }

    protected function ProcessComplexJsonFields($entry)
    {
        $fieldsString = '';
        foreach ($entry as $field) {

            // check validations for array.
            if(!empty($field->validations))
            {
                $v = explode("|",$field->validations);
                if(in_array("required",$v))
                {
                    $field->required = true;
                }
                
            }

            switch ($field->type) {
                case 'select':
                    $FieldArray[] = (array) $field;
                    $fieldsString .= $field->name . '#' . $field->type . '#options=' . implode(',', $field->options) . ';';
                    break;
                case 'OneToMany':
                    // re-run the code to generate another controller
                    // $this->CreateControllerFromObj($field->data);
                    // $this->CreateModelFromObj($field->data);
                    break;

                default:
                    $FieldArray[] = (array) $field;
                    $modifier     = (isset($field->modifier)) ? '#' . $field->modifier : '';
                    $fieldsString .= $field->name . '#' . $field->type . $modifier . ';';
                    break;
            }

        }

        $fieldsString = rtrim($fieldsString, ';');
        return [$fieldsString, $FieldArray];
    }

}
