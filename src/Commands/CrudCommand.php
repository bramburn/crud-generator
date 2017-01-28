<?php

namespace Appzcoder\CrudGenerator\Commands;

use File;
use Illuminate\Console\Command;

class CrudCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crud:generate
                            {name : The name of the Crud.}
                            {--fields= : Fields name for the form & migration.}
                            {--complexjson= : Create Complex CRUD with multiple relationship easily.}
                            {--fields_from_file= : Fields from a json file.}
                            {--validations= : Validation details for the fields.}
                            {--controller-namespace= : Namespace of the controller.}
                            {--model-namespace= : Namespace of the model inside "app" dir.}
                            {--pk=id : The name of the primary key.}
                            {--pagination=25 : The amount of models per page for index pages.}
                            {--indices= : The fields to add an index to.}
                            {--foreign-keys= : The foreign keys for the table.}
                            {--relationships= : The relationships for the model.}
                            {--route=yes : Include Crud route to routes.php? yes|no.}
                            {--route-path= : Prefix of the route group.}
                            {--view-path= : The name of the view path.}
                            {--localize=no : Allow to localize? yes|no.}
                            {--locales=en : Locales language type.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Crud including controller, model, views & migrations.';

    /** @var string  */
    protected $FinalRouteName = '';

    /** @var string  */
    protected $controller = '';

/** @var array  */
    protected $bufferedCalls = array();

    // config = test mode
    protected $testMode = false;

    protected $complexCRUD;

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
        $name          = $this->argument('name');
        $modelName     = str_singular($name);
        $migrationName = str_plural(snake_case($name));
        $tableName     = $migrationName;

        $routePath            = $this->option('route-path'); //changed route-group to route-path as it makes sense...
        $this->FinalRouteName = ($routePath) ? $routePath . '/' . snake_case($name, '-') : snake_case($name, '-'); //this is the Final route path and name. This is what you put in your URL browser to get to this CRUD.

        // Starts complex Json

        if ($this->option('complexjson')) {
            $this->info('Running Complex Json...');

            $this->complexCRUD = new \Appzcoder\CrudGenerator\shared\CRUDcomplexClass();

            $this->ProcessComplexJson($this->option('complexjson'));
            $this->FinaliseCalls();

        } else {

            $perPage = intval($this->option('pagination'));

            $controllerNamespace = ($this->option('controller-namespace')) ? $this->option('controller-namespace') . '\\' : '';
            $modelNamespace      = ($this->option('model-namespace')) ? trim($this->option('model-namespace')) . '\\' : '';

            $fields = rtrim($this->option('fields'), ';');

            if ($this->option('fields_from_file')) {
                $fields = $this->processJSONFields($this->option('fields_from_file'));
            }

            $primaryKey = $this->option('pk');
            $viewPath   = $this->option('view-path');

            $foreignKeys = $this->option('foreign-keys');

            $fillable = $this->PostProcessFieldsForModels($fields); //new

            $localize = $this->option('localize');
            $locales  = $this->option('locales');

            $indices       = $this->option('indices');
            $relationships = $this->option('relationships');

            $validations = trim($this->option('validations'));

            $this->call('crud:controller', [
                'name'              => $controllerNamespace . $name . 'Controller',
                '--crud-name'       => $name,
                '--model-name'      => $modelName,
                '--model-namespace' => $modelNamespace,
                '--view-path'       => $viewPath,
                '--route-path'      => $routePath,
                '--pagination'      => $perPage,
                '--fields'          => $fields,
                '--validations'     => $validations,
            ]);
            $this->call('crud:model', [
                'name'            => $modelNamespace . $modelName,
                '--fillable'      => $fillable,
                '--table'         => $tableName,
                '--pk'            => $primaryKey,
                '--relationships' => $relationships,
            ]);
            $this->call('crud:migration', [
                'name'           => $migrationName,
                '--schema'       => $fields,
                '--pk'           => $primaryKey,
                '--indices'      => $indices,
                '--foreign-keys' => $foreignKeys,
            ]);
            $this->call('crud:view', [
                'name'          => $name,
                '--fields'      => $fields,
                '--validations' => $validations,
                '--view-path'   => $viewPath,
                '--route-path'  => $routePath,
                '--localize'    => $localize,
                '--pk'          => $primaryKey,
            ]);

            if ($localize == 'yes') {
                $this->call('crud:lang', ['name' => $name, '--fields' => $fields, '--locales' => $locales]);
            }

            // For optimizing the class loader
            $this->callSilent('optimize');
            $_controllerName = ($controllerNamespace != '') ? $controllerNamespace . '\\' . $name . 'Controller' : $name . 'Controller';
            $this->ProcessRoute($_controllerName, $this->FinalRouteName);
        }

    }

    /**
     * undocumented function
     *
     * @return void
     * @author
     **/
    protected function ProcessRoute($_controllerName, $FinalRouteName)
    {

        // Updating the Http/routes.php file
        $routeFile = app_path('Http/routes.php');

        if (\App::VERSION() >= '5.3') {
            $routeFile = base_path('routes/web.php');
        }

        if (file_exists($routeFile) && (strtolower($this->option('route')) === 'yes')) {

            $isAdded = File::append($routeFile, "\n" . "Route::resource('" . $FinalRouteName . "', '" . $_controllerName . "');");

            if ($isAdded) {
                $this->info('Crud/Resource route added to ' . $routeFile);
                $this->info('Your Route is ' . $FinalRouteName);
            } else {
                $this->info('Unable to add the route to ' . $routeFile);
            }
        }
    }

    /**
     * Process the JSON Fields.
     *
     * @param  string $file
     *
     * @return string
     */
    protected function processJSONFields($file)
    {
        $json   = File::get($file);
        $fields = json_decode($json);

        $fieldsString = '';
        foreach ($fields->fields as $field) {
            if ($field->type == 'select') {
                $fieldsString .= $field->name . '#' . $field->type . '#options=' . implode(',', $field->options) . ';';
            } else {
                $fieldsString .= $field->name . '#' . $field->type . ';';
            }
        }

        $fieldsString = rtrim($fieldsString, ';');

        return $fieldsString;
    }

    /**
     * This processes a complex json field which includes a lot of information.
     *
     * @return BULL
     * @author bramburn (icelabz.co.uk)
     **/
    protected function ProcessComplexJson($fullFilePath)
    {
        try
        {
            $json = File::get($fullFilePath);
        } catch (Illuminate\Filesystem\FileNotFoundException $exception) {
            $this->error("File does not exists");
        }

        // decode the JSON

        $data = json_decode($json);

        foreach ($data as $entry) {
            // each entry is a CRUD

            foreach ($entry as $crud_entry) {
                // $this->CreateControllerFromObj($crud_entry); // only creates the Controller Commands
                // $this->CreateModelFromObj($crud_entry); // only creates the Model commands
                $this->PreProcessMainData($crud_entry);
            }

            // $this->info("Creating CRUD - ".$entry-)
        }
        // $this->info(print_r($data, 1));
        // log::info(print_r($data->CRUD, 1));

    }

    /**
     * This checks and validates the main data set
     *
     * @return array
     * @author bramburn (icelabz.co.uk)
     **/
    protected function PreProcessMainData($dataset, $parentArray = array(), $fieldsToAdd = null, $belongsTo = "", $foreignKeyToAdd = "")
    {

        $to_check = [
            "name",
            "route",
            "viewPath",
            "routePath",
            "perPage",
            "controller_namespace",
            "modelNamespace",
            "validations",
            "relationships",
            "primaryKey",
            "indices",
            "foreignKeys",
        ];

        //Set Model Name

        $dataset->modelName       = str_singular($dataset->name);
        $dataset->migrationName   = str_plural(snake_case($dataset->name));
        $dataset->tableName       = $dataset->migrationName;
        $dataset->controllerClass = $dataset->controller_namespace . $dataset->name . "Controller";
        $dataset->primaryKey      = ($dataset->primaryKey) ? $dataset->primaryKey : 'id';
        $dataset->viewPath        = rtrim(($dataset->viewPath) ? $dataset->viewPath : '', '/');
        $dataset->FinalRouteName  = ($dataset->routePath) ? $dataset->routePath . '/' . snake_case($dataset->name, '-') : snake_case($dataset->name, '-');
        $dataset->routePath       = ($dataset->routePath) ? $dataset->routePath : '';
        $dataset->modelName       = ($dataset->modelName) ? $dataset->modelName : str_singular($dataset->name);
        $dataset->modelNamespace  = ($dataset->modelNamespace) ? $dataset->modelNamespace : '';
        $dataset->modelClass      = $dataset->modelNamespace . $dataset->modelName;
        $viewContainerFolder      = snake_case($dataset->name, '-');

        foreach ($to_check as $key) {
            if (isset($dataset->$key)) {
                $this->info("Found " . $key);
            } else {
                $this->error("Cannot find " . $key);
            }

        }

        $relationshipString = "";
        if (!is_null($fieldsToAdd)) {

            array_push($dataset->data->fields, $fieldsToAdd);
        }

        if (!empty($parentArray)) {
            $this->comment('We have some data from the parent CRUD');
        }

        // check fields
        foreach ($dataset->data->fields as $field) {
            if ($field->type == "OneToMany") {
                $this->info("Found a childset " . $field->name);

                // creates belongs to
                $parentid = str_singular($dataset->name) . "_id";

                $_fieldToAdd = (object) [
                    "name"           => $parentid,
                    "type"           => "integer",
                    "showform"       => "no",
                    "ParentDropDown" => true,
                    "showInIndex"    => "no",
                    "modifier"       => 'unsigned',
                ];
                $fk               = str_singular($dataset->name) . "_id";
                $_belongsTo       = str_singular($dataset->name) . '#belongsTo#App\\' . $dataset->modelClass . '|' . $fk . '|id';
                $foreignKeyString = $fk . '#id#' . $dataset->tableName . '#cascade#cascade';

                // send parent data through to the child class
                $_parentArray = [
                    'parent_modelClass' => $dataset->modelClass, //namespance + class name

                ];

                $this->PreProcessMainData($field->data, $_parentArray, $_fieldToAdd, $_belongsTo, $foreignKeyString);
                // add hasMany
                $relationshipString .= str_plural($field->data->name) . '#hasMany#App\\' . $field->data->modelNamespace . $field->data->modelName . '|' . $fk . '|id,';

                $this->info("continue.... ");
            } else {
                $this->info("Field:: " . $field->name . ' :: ' . $field->type);
            }

        }

        // Finalise relationship Strings
        $dataset->relationships = rtrim($relationshipString . $belongsTo . $dataset->relationships, ',');
        $dataset->foreignKeys   = rtrim($foreignKeyToAdd . $dataset->foreignKeys, ',');

        // Check Classes

        $classToCheck = [

            $dataset->controllerClass,
            $dataset->modelNamespace . $dataset->modelName,
        ];

        foreach ($classToCheck as $class) {
            if (class_exists($class)) {
                $this->error("Class " . $class . " exists already");
            } else {

                $this->info("Class " . $class . " does not exists...");
            }
        }

        if (file_exists('resources/views/' . $dataset->viewPath)) {
            $this->error("File resources/views/ " . $dataset->viewPath . " Exists!");
        }

        list($fields, $FieldArray) = $this->ProcessComplexJsonFields($dataset->data->fields); //if there are any child fields it will run Create
        $fillable                  = $this->PostProcessFieldsForModels($fields);

        $this->info('Fields ::::: ' . $fields);
        $this->info('fillable ::::: ' . $fillable);

        // $choice = $this->anticipate('Do you want to proceed?', ['Yes', 'No']);
        $this->info('create controller ' . $dataset->controllerClass);

        //if the toProcess is not set then process, or if the data is set then we check if controller is allowed to be produced.
        if (!isset($dataset->toProcess) or in_array("controller", $dataset->toProcess)) {

            $this->BufferCalls('crud:controller', [
                'name'                         => $dataset->controllerClass, //this is the fullpath and name+Controller suffix of the controller including the namespace
                '--crud-name'                  => $dataset->name, //name of the CRUD, filename and classname (this does not include the namespace)
                '--model-name'                 => $dataset->modelName, //here we need to let the system know what model we are using for this. This is going to be the filename +class name from reading the stub templates.
                '--model-namespace'            => $dataset->modelNamespace, //do we have any namespace for the model?
                '--view-path'                  => $dataset->viewPath, //this is the view folder location /resources/views/xxxx it needs to remove any trailing slash.... if blank it will be saved directly in /resourves/views/{here}
                '--route-path'                 => $dataset->routePath, //Prefix of the route, it is the path to your CRUD. It does not include the file name, just the structured path.
                '--pagination'                 => ($dataset->perPage) ? $dataset->perPage : 10,
                '--fields'                     => $fields,
                '--validations'                => ($dataset->validations) ? $dataset->validations : '',
                '--parent-model-class'         => (isset($parentArray['parent_modelClass'])) ? $parentArray['parent_modelClass'] : null,
                '--parent-field-select-format' => (isset($dataset->ParentListFormat)) ? $dataset->ParentListFormat : null,
                '--parent-field'               => (isset($fieldsToAdd->name)) ? $fieldsToAdd->name : null,
            ]);

        }

        //if the toProcess is not set then process, or if the data is set then we check if model is allowed to be produced.
        if (!isset($dataset->toProcess) or in_array("model", $dataset->toProcess)) {
            $this->BufferCalls('crud:model', [
                'name'            => $dataset->modelClass,
                '--fillable'      => $fillable, //from post process
                '--table'         => $dataset->tableName,
                '--pk'            => '',
                '--relationships' => $dataset->relationships, //this needs a bit of working
            ]);
        }

        //if the toProcess is not set then process, or if the data is set then we check if migration is allowed to be produced.
        if (!isset($dataset->toProcess) or in_array("migration", $dataset->toProcess)) {
            $this->BufferCalls('crud:migration', [
                'name'           => $dataset->migrationName,
                '--schema'       => $fields,
                '--pk'           => $dataset->primaryKey,
                '--indices'      => $dataset->indices,
                '--foreign-keys' => $dataset->foreignKeys,
            ]);

        }



        //if the toProcess is not set then process, or if the data is set then we check if view is allowed to be produced.
        if (!isset($dataset->toProcess) or in_array("view", $dataset->toProcess)) {
            $info = [
                'routePath'           => $dataset->routePath,
                'modelName'           => $dataset->modelName,
                'primaryKey'          => $dataset->primaryKey,
                'viewPath'            => $dataset->viewPath,
                'viewContainerFolder' => $viewContainerFolder,

            ];

            $this->complexCRUD->ProcessComplexJsonFieldsForView($dataset->name, $FieldArray, $info);
        }

        //if the route is not set then process
        if (!isset($dataset->route) or $dataset->route == 'yes') {
            $this->ProcessRoute($dataset->controllerClass, $dataset->FinalRouteName);
        }

        // I am a child; here are some information you need to know about me

        $childData = [
        'FullChildRoutePath'=>$dataset->viewPath,
        'relationshipModelName'=>

        ];

        return 

    }

/**
 * Finish off the calls in one go
 *
 * @return output
 * @author bramburn (icelabz.co.uk)
 **/
    protected function FinaliseCalls()
    {
        krsort($this->bufferedCalls);

        foreach ($this->bufferedCalls as $key => $value) {

            if ($value[0] == 'crud:migration') {
                $value[1]['--dateprefix'] = date('Y_m_d_His', strtotime("-" . $key . " sec"));
            }

            if ($this->testMode == false) {
                $this->call($value[0], $value[1]);
            } else {
                // print_r($value);
            }

        }
        $this->callSilent('optimize');

    }

/**
 * delays the call to sequence it properly
 *
 * @return array
 * @author bramburn (icelabz.co.uk)
 **/
    public function BufferCalls($call, $data)
    {
        $this->bufferedCalls[] = [$call, $data];
    }

/**
 * undocumented function
 *
 * @return void
 * @author bramburn (icelabz.co.uk)
 **/
    protected function PreProcessFields($crud_entry)
    {
        $fields   = $this->ProcessComplexJsonFields($crud_entry->data->fields); //if there are any child fields it will run Create
        $fillable = $this->PostProcessFieldsForModels($fields);

        return [$fields, $fillable];
    }

/**
 * Processes the fields to Command syntax for crud:model
 *
 * @return string
 * @author bramburn
 **/
    protected function PostProcessFieldsForModels($fields)
    {
        $fieldsArray   = explode(';', $fields);
        $fillableArray = [];

        foreach ($fieldsArray as $item) {
            $spareParts      = explode('#', trim($item));
            $fillableArray[] = $spareParts[0];
        }

        $commaSeparetedString = implode("', '", $fillableArray);
        $fillable             = "['" . $commaSeparetedString . "']";

        return $fillable;
    }

/**
 * Processes the new complex json fields
 *
 * @return fieldString
 * @author bramburn
 **/
    protected function ProcessComplexJsonFields($entry)
    {
        $fieldsString = '';
        foreach ($entry as $field) {

            // check validations for array.
            if (!empty($field->validations)) {
                $v = explode("|", $field->validations);
                if (in_array("required", $v)) {
                    $field->required = true;
                }

            } else {
                $field->required = false;
            }

            switch ($field->type) {
                case 'select':

                    $FieldArray[] = (array) $field;
                    $fieldsString .= $field->name . '#' . $field->type . '#options=' . implode(',', $field->options) . ';';
                    break;
                case 'OneToMany':
                    // do nothing
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
