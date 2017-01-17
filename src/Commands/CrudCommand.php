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
                            {--route-group= : Prefix of the route group.}
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
    protected $routeName = '';

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

        if ($this->option('complexjson')) {
            $this->ProcessComplexJson($this->option('complexjson'));
            $this->info('running boss mode');
            exit();
        }

        $routeGroup      = $this->option('route-group');
        $this->routeName = ($routeGroup) ? $routeGroup . '/' . snake_case($name, '-') : snake_case($name, '-');
        $perPage         = intval($this->option('pagination'));

        $controllerNamespace = ($this->option('controller-namespace')) ? $this->option('controller-namespace') . '\\' : '';
        $modelNamespace      = ($this->option('model-namespace')) ? trim($this->option('model-namespace')) . '\\' : '';

        $fields = rtrim($this->option('fields'), ';');

        if ($this->option('fields_from_file')) {
            $fields = $this->processJSONFields($this->option('fields_from_file'));
        }

        $primaryKey = $this->option('pk');
        $viewPath   = $this->option('view-path');

        $foreignKeys = $this->option('foreign-keys');

        $fieldsArray   = explode(';', $fields);
        $fillableArray = [];

        foreach ($fieldsArray as $item) {
            $spareParts      = explode('#', trim($item));
            $fillableArray[] = $spareParts[0];
        }

        $commaSeparetedString = implode("', '", $fillableArray);
        $fillable             = "['" . $commaSeparetedString . "']";

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
            '--route-group'     => $routeGroup,
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
            '--route-group' => $routeGroup,
            '--localize'    => $localize,
            '--pk'          => $primaryKey,
        ]);

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
        return ["Route::resource('" . $this->routeName . "', '" . $this->controller . "');"];
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
                if (class_exists($crud_entry->controller_namespace, $crud_entry->name)) {
                    $this->error("Class " . $crud_entry->controller_namespace, $crud_entry->name . " exists already");
                } else {

                    $this->info("Class " . $crud_entry->controller_namespace, $crud_entry->name . " does not exists...creating it now");
                    // generating fields
                    $fields = $this->ProcessComplexJsonFields($crud_entry->data->fields);

                    $this->info('crud:controller' . print_r([
                        'name'              => $crud_entry->controller_namespace . $crud_entry->name . 'Controller',
                        '--crud-name'       => $crud_entry->name,
                        '--model-name'      => ($crud_entry->modelName) ? $crud_entry->modelName : str_singular($crud_entry->name),
                        '--model-namespace' => ($crud_entry->modelNamespace) ? $crud_entry->modelNamespace : '',
                        '--view-path'       => ($crud_entry->routePath) ? $crud_entry->routePath : '', //changed it from viewPath to routePath as it made more sense
                        '--route-group'     => ($crud_entry->routeGroup) ? $crud_entry->routeGroup : '/',
                        '--pagination'      => ($crud_entry->perPage) ? $crud_entry->perPage : '',
                        '--fields'          => $fields,
                        '--validations'     => ($crud_entry->validations) ? $crud_entry->validations : ''], 1));
                }

            }

            // $this->info("Creating CRUD - ".$entry-)
        }
        // $this->info(print_r($data, 1));
        // log::info(print_r($data->CRUD, 1));

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
                case 'oneToMany':
                    // don't do anything for now as this needs to be RE-run in a function for now.
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
