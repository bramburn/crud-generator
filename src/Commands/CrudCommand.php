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
                            {--indexes= : The fields to add an index to.}
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
            $this->ProcessComplexJson($this->option('complexjson'));
            $this->info('running boss mode');

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

            $indexes       = $this->option('indexes');
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
                '--indexes'      => $indexes,
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
        }

        if ($localize == 'yes') {
            $this->call('crud:lang', ['name' => $name, '--fields' => $fields, '--locales' => $locales]);
        }
        // For optimizing the class loader
        $this->callSilent('optimize');

        // Updating the Http/routes.php file
        $routeFile = app_path('Http/routes.php');

        if (\App::VERSION() >= '5.3') {
            $routeFile = base_path('routes/web.php');
        }

        if (file_exists($routeFile) && (strtolower($this->option('route')) === 'yes')) {
            $this->controller = ($controllerNamespace != '') ? $controllerNamespace . '\\' . $name . 'Controller' : $name . 'Controller';

            $isAdded = File::append($routeFile, "\n" . implode("\n", $this->addRoutes()));

            if ($isAdded) {
                $this->info('Crud/Resource route added to ' . $routeFile);
                $this->info('Your Route is ' . $this->FinalRouteName);
            } else {
                $this->info('Unable to add the route to ' . $routeFile);
            }
        }
    }

    /**
     * Add routes.
     *
     * @return  array
     */
    protected function addRoutes()
    {
        return ["Route::resource('" . $this->FinalRouteName . "', '" . $this->controller . "');"];
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

        $data = json_decode($json);
        // $required_data = ['routePath'];

        foreach ($data as $entry) {
            // each entry is a CRUD

            foreach ($entry as $crud_entry) {
                $this->CreateControllerFromObj($crud_entry);
            }

            // $this->info("Creating CRUD - ".$entry-)
        }
        // $this->info(print_r($data, 1));
        // log::info(print_r($data->CRUD, 1));

    }

    /**
     * This processes the crud entry to generate the model
     *
     * @return N/A
     * @author bramburn (icelabz.co.uk)
     **/
    protected function CreateModelFromObj($crud_entry)
    {
        $currentModelClass = $crud_entry->modelNamespace . $crud_entry->modelName; //this is the full path of the model class, we'll check if it exists too!

        if (class_exists($currentModelClass)) {
            $this->error("Model " . $currentModelClass . " exists already");
        } else {

            $this->info("Model " . $currentModelClass . " does not exists...creating it now");
            // generating fields
            $fields   = $this->ProcessComplexJsonFields($crud_entry->data->fields);
            $fillable = $this->PostProcessFieldsForModels($fields);

            // create model
            $this->call('crud:model', [
                'name'            => ($currentModelClass) ? $currentModelClass : '' . ($crud_entry->modelName) ? $crud_entry->modelName : str_singular($crud_entry->name),
                '--fillable'      => $fillable, //from post process
                '--table'         => ($crud_entry->tableName) ? $crud_entry->tableName : str_plural(snake_case($crud_entry->name)),
                '--pk'            => '',
                '--relationships' => $crud_entry->relationships, //this needs a bit of working
            ]);

            // add the resource to the route/web
        }
    }

    /**
     * This generates and calls the CRUD:controller
     *
     * @return n/a
     * @author bramburn (icelabz.co.uk)
     **/
    protected function CreateControllerFromObj($crud_entry)
    {

        $currentControllerClass = $crud_entry->controller_namespace . $crud_entry->name; //this is the full path of the class, we'll check if it exists first! if not we stop.

        /**
         * @todo: split this function for controller, model, view, migration so that it can be easily ran one by one
         *
         */

        if (class_exists($currentControllerClass)) {
            $this->error("Class " . $currentControllerClass . " exists already");
        } else {

            $this->info("Class " . $currentControllerClass . " does not exists...creating it now");
            // generating fields
            $fields = $this->ProcessComplexJsonFields($crud_entry->data->fields);

            $this->call('crud:controller', [
                'name'              => $currentControllerClass . 'Controller', //this is the fullpath and name of the controller including the namespace
                '--crud-name'       => $crud_entry->name, //name of the CRUD, filename and classname (this does not include the namespace)
                '--model-name'      => ($crud_entry->modelName) ? $crud_entry->modelName : str_singular($crud_entry->name), //here we need to let the system know what model we are using for this. This is going to be the filename +class name from reading the stub templates.
                '--model-namespace' => ($crud_entry->modelNamespace) ? $crud_entry->modelNamespace : '', //do we have any namespace for the model?
                '--view-path'       => rtrim(($crud_entry->viewPath) ? $crud_entry->viewPath : '', '/'), //this is the view folder location /resources/views/xxxx it needs to remove any trailing slash.... if blank it will be saved directly in /resourves/views/{here}
                '--route-path'      => ($crud_entry->routePath) ? $crud_entry->routePath : '', //Prefix of the route, it is the path to your CRUD. It does not include the file name, just the structured path.
                '--pagination'      => ($crud_entry->perPage) ? $crud_entry->perPage : 10,
                '--fields'          => $fields,
                '--validations'     => ($crud_entry->validations) ? $crud_entry->validations : '']);

        }

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

            switch ($field->type) {
                case 'select':
                    $fieldsString .= $field->name . '#' . $field->type . '#options=' . implode(',', $field->options) . ';';
                    break;
                case 'OneToMany':
                    // re-run the code to generate another controller
                    $this->CreateControllerFromObj($field->data);
                    break;

                default:
                    $fieldsString .= $field->name . '#' . $field->type . ';';
                    break;
            }

        }

        $fieldsString = rtrim($fieldsString, ';');
        return $fieldsString;
    }
}
