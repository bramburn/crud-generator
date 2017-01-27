<?php

namespace Appzcoder\CrudGenerator\Commands;

use File;
use Illuminate\Console\Command;

class CrudViewCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crud:view
                            {name : The name of the Crud.}
                            {--fields= : The fields name for the form.}
                            {--view-path= : The name of the view path.}
                            {--route-path= : Prefix of the route, it is path to the CRUD}
                            {--pk=id : The name of the primary key.}
                            {--validations= : Validation details for the fields.}
                            {--localize=no : Localize the view? yes|no.}
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create views for the Crud.';

    /**
     * View Directory Path.
     *
     * @var string
     */
    protected $viewDirectoryPath;

    /**
     *  Form field types collection.
     *
     * @var array
     */
    protected $typeLookup = [
        'string'     => 'text',
        'char'       => 'text',
        'varchar'    => 'text',
        'text'       => 'textarea',
        'mediumtext' => 'textarea',
        'longtext'   => 'textarea',
        'json'       => 'textarea',
        'jsonb'      => 'textarea',
        'binary'     => 'textarea',
        'password'   => 'password',
        'email'      => 'email',
        'number'     => 'number',
        'integer'    => 'number',
        'bigint'     => 'number',
        'mediumint'  => 'number',
        'tinyint'    => 'number',
        'smallint'   => 'number',
        'decimal'    => 'number',
        'double'     => 'number',
        'float'      => 'number',
        'date'       => 'date',
        'datetime'   => 'datetime-local',
        'timestamp'  => 'datetime-local',
        'time'       => 'time',
        'boolean'    => 'radio',
        'enum'       => 'select',
        'select'     => 'select',
        'file'       => 'file',
    ];

    /**
     * Form's fields.
     *
     * @var array
     */
    protected $formFields = [];

    /**
     * Html of Form's fields.
     *
     * @var string
     */
    protected $formFieldsHtml = '';

    /**
     * Number of columns to show from the table. Others are hidden.
     *
     * @var integer
     */
    protected $defaultColumnsToShow = 3;

    /**
     * Name of the Crud.
     *
     * @var string
     */
    protected $crudName = '';

    /**
     * Crud Name in capital form.
     *
     * @var string
     */
    protected $crudNameCap = '';

    /**
     * Crud Name in singular form.
     *
     * @var string
     */
    protected $crudNameSingular = '';

    /**
     * Primary key of the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Name of the Model.
     *
     * @var string
     */
    protected $modelName = '';

    /**
     * Name of the View Dir.
     *
     * @var string
     */
    protected $viewContainerFolder = '';

    /**
     * Name or prefix of the Route Group.
     *
     * @var string
     */
    protected $routePath = '';

    /**
     * Html of the form heading.
     *
     * @var string
     */
    protected $formHeadingHtml = '';

    /**
     * Html of the form body.
     *
     * @var string
     */
    protected $formBodyHtml = '';

    /**
     * Html of view to show.
     *
     * @var string
     */
    protected $formBodyHtmlForShowView = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->viewDirectoryPath = config('crudgenerator.custom_template') ? config('crudgenerator.path') : __DIR__ . '/../stubs/';

        if (config('crudgenerator.view_columns_number')) {
            $this->defaultColumnsToShow = config('crudgenerator.view_columns_number');
        }
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->crudName            = strtolower($this->argument('name'));
        $this->crudNameCap         = ucwords($this->crudName);
        $this->crudNameSingular    = str_singular($this->crudName);
        $this->modelName           = str_singular($this->argument('name'));
        $this->primaryKey          = $this->option('pk');
        $this->routePath           = ($this->option('route-path')) ? $this->option('route-path') . '/' : $this->option('route-path');
        $this->viewContainerFolder = snake_case($this->argument('name'), '-');
        $this->userViewPath        = ($this->option('view-path')) ? $this->option('view-path') : null;

        // check if the view directory exists
        $path = $this->CreateDirectory($this->userViewPath, $this->viewContainerFolder);

        $fields      = $this->option('fields');
        $fieldsArray = explode(';', $fields);

        $this->formFields = [];

        $validations = $this->option('validations');

        if ($fields) {
            $x = 0;
            foreach ($fieldsArray as $item) {
                $itemArray = explode('#', $item);

                $this->formFields[$x]['name']     = trim($itemArray[0]);
                $this->formFields[$x]['type']     = trim($itemArray[1]);
                $this->formFields[$x]['required'] = preg_match('/' . $itemArray[0] . '/', $validations) ? true : false;

                if ($this->formFields[$x]['type'] == 'select' && isset($itemArray[2])) {
                    $options      = trim($itemArray[2]);
                    $options      = str_replace('options=', '', $options);
                    $optionsArray = explode(',', $options);

                    $commaSeparetedString = implode("', '", $optionsArray);
                    $options              = "['" . $commaSeparetedString . "']";

                    $this->formFields[$x]['options'] = $options;
                }

                $x++;
            }
        }

        foreach ($this->formFields as $item) {
            $this->formFieldsHtml .= $this->createField($item);
        }

        $i = 0;
        foreach ($this->formFields as $key => $value) {
            if ($i == $this->defaultColumnsToShow) {
                break;
            }

            $field = $value['name'];
            $label = ucwords(str_replace('_', ' ', $field));
            if ($this->option('localize') == 'yes') {
                $label = '{{ trans(\'' . $this->crudName . '.' . $field . '\') }}';
            }
            $this->formHeadingHtml .= '<th> ' . $label . ' </th>';
            $this->formBodyHtml .= '<td>{{ $item->' . $field . ' }}</td>';
            $this->formBodyHtmlForShowView .= '<tr><th> ' . $label . ' </th><td> {{ $%%crudNameSingular%%->' . $field . ' }} </td></tr>';

            $i++;
        }

        // For index.blade.php file
        $indexFile    = $this->viewDirectoryPath . 'index.blade.stub';
        $newIndexFile = $path . 'index.blade.php';
        if (!File::copy($indexFile, $newIndexFile)) {
            echo "failed to copy $indexFile...\n";
        } else {
            $this->templateIndexVars($newIndexFile);
        }

        // For form.blade.php file
        $formFile    = $this->viewDirectoryPath . 'form.blade.stub';
        $newFormFile = $path . 'form.blade.php';
        if (!File::copy($formFile, $newFormFile)) {
            echo "failed to copy $formFile...\n";
        } else {
            $this->templateFormVars($newFormFile);
        }

        // For create.blade.php file
        $createFile    = $this->viewDirectoryPath . 'create.blade.stub';
        $newCreateFile = $path . 'create.blade.php';
        if (!File::copy($createFile, $newCreateFile)) {
            echo "failed to copy $createFile...\n";
        } else {
            $this->templateCreateVars($newCreateFile);
        }

        // For edit.blade.php file
        $editFile    = $this->viewDirectoryPath . 'edit.blade.stub';
        $newEditFile = $path . 'edit.blade.php';
        if (!File::copy($editFile, $newEditFile)) {
            echo "failed to copy $editFile...\n";
        } else {
            $this->templateEditVars($newEditFile);
        }

        // For show.blade.php file
        $showFile    = $this->viewDirectoryPath . 'show.blade.stub';
        $newShowFile = $path . 'show.blade.php';
        if (!File::copy($showFile, $newShowFile)) {
            echo "failed to copy $showFile...\n";
        } else {
            $this->templateShowVars($newShowFile);
        }

        $this->info('View created successfully.');
    }

    

   


    /**
     * Update values between %% with real values in index view.
     *
     * @param  string $newIndexFile
     *
     * @return void
     */
    public function templateIndexVars($newIndexFile)
    {
        File::put($newIndexFile, str_replace('%%formHeadingHtml%%', $this->formHeadingHtml, File::get($newIndexFile)));
        File::put($newIndexFile, str_replace('%%formBodyHtml%%', $this->formBodyHtml, File::get($newIndexFile)));
        File::put($newIndexFile, str_replace('%%crudName%%', $this->crudName, File::get($newIndexFile)));
        File::put($newIndexFile, str_replace('%%crudNameCap%%', $this->crudNameCap, File::get($newIndexFile)));
        File::put($newIndexFile, str_replace('%%modelName%%', $this->modelName, File::get($newIndexFile)));
        File::put($newIndexFile, str_replace('%%viewContainerFolder%%', $this->viewContainerFolder, File::get($newIndexFile)));
        File::put($newIndexFile, str_replace('%%routePath%%', $this->routePath, File::get($newIndexFile)));
        File::put($newIndexFile, str_replace('%%primaryKey%%', $this->primaryKey, File::get($newIndexFile)));
    }

    /**
     * Update values between %% with real values in form view.
     *
     * @param  string $newFormFile
     *
     * @return void
     */
    public function templateFormVars($newFormFile)
    {
        File::put($newFormFile, str_replace('%%formFieldsHtml%%', $this->formFieldsHtml, File::get($newFormFile)));
    }

    /**
     * Update values between %% with real values in create view.
     *
     * @param  string $newCreateFile
     *
     * @return void
     */
    public function templateCreateVars($newCreateFile)
    {
        $viewTemplateDir = isset($this->userViewPath) ? $this->userViewPath . '.' . $this->viewContainerFolder : $this->viewContainerFolder;

        File::put($newCreateFile, str_replace('%%crudName%%', $this->crudName, File::get($newCreateFile)));
        File::put($newCreateFile, str_replace('%%crudNameCap%%', $this->crudNameCap, File::get($newCreateFile)));
        File::put($newCreateFile, str_replace('%%modelName%%', $this->modelName, File::get($newCreateFile)));
        File::put($newCreateFile, str_replace('%%viewContainerFolder%%', $this->viewContainerFolder, File::get($newCreateFile)));
        File::put($newCreateFile, str_replace('%%routePath%%', $this->routePath, File::get($newCreateFile)));
        File::put($newCreateFile, str_replace('%%viewTemplateDir%%', $viewTemplateDir, File::get($newCreateFile)));
    }

    /**
     * Update values between %% with real values in edit view.
     *
     * @param  string $newEditFile
     *
     * @return void
     */
    public function templateEditVars($newEditFile)
    {
        $viewTemplateDir = isset($this->userViewPath) ? $this->userViewPath . '.' . $this->viewContainerFolder : $this->viewContainerFolder;

        File::put($newEditFile, str_replace('%%crudName%%', $this->crudName, File::get($newEditFile)));
        File::put($newEditFile, str_replace('%%crudNameSingular%%', $this->crudNameSingular, File::get($newEditFile)));
        File::put($newEditFile, str_replace('%%crudNameCap%%', $this->crudNameCap, File::get($newEditFile)));
        File::put($newEditFile, str_replace('%%modelName%%', $this->modelName, File::get($newEditFile)));
        File::put($newEditFile, str_replace('%%viewContainerFolder%%', $this->viewContainerFolder, File::get($newEditFile)));
        File::put($newEditFile, str_replace('%%routePath%%', $this->routePath, File::get($newEditFile)));
        File::put($newEditFile, str_replace('%%primaryKey%%', $this->primaryKey, File::get($newEditFile)));
        File::put($newEditFile, str_replace('%%viewTemplateDir%%', $viewTemplateDir, File::get($newEditFile)));
    }

    /**
     * Update values between %% with real values in show view.
     *
     * @param  string $newShowFile
     *
     * @return void
     */
    public function templateShowVars($newShowFile)
    {
        File::put($newShowFile, str_replace('%%formHeadingHtml%%', $this->formHeadingHtml, File::get($newShowFile)));
        File::put($newShowFile, str_replace('%%formBodyHtmlForShowView%%', $this->formBodyHtmlForShowView, File::get($newShowFile)));
        File::put($newShowFile, str_replace('%%crudName%%', $this->crudName, File::get($newShowFile)));
        File::put($newShowFile, str_replace('%%crudNameSingular%%', $this->crudNameSingular, File::get($newShowFile)));
        File::put($newShowFile, str_replace('%%crudNameCap%%', $this->crudNameCap, File::get($newShowFile)));
        File::put($newShowFile, str_replace('%%modelName%%', $this->modelName, File::get($newShowFile)));
        File::put($newShowFile, str_replace('%%primaryKey%%', $this->primaryKey, File::get($newShowFile)));
        File::put($newShowFile, str_replace('%%viewContainerFolder%%', $this->viewContainerFolder, File::get($newShowFile)));
        File::put($newShowFile, str_replace('%%routePath%%', $this->routePath, File::get($newShowFile)));
    }

    
}
