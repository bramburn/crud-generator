<?php

namespace Appzcoder\CrudGenerator\Commands;

use Illuminate\Console\GeneratorCommand;

class CrudControllerCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crud:controller
                            {name : The name of the controler including the Namespace.}
                            {--crud-name= : The name of the CRUD.}
                            {--model-name= : The name of the Model.}
                            {--model-namespace= : The namespace of the Model.}
                            {--view-path= : The name of the view path.}
                            {--fields= : Fields name for the form & migration.}
                            {--validations= : Validation details for the fields.}
                            {--route-path= : Prefix of the route, it is the path to your CRUD}
                            {--pagination=25 : The amount of models per page for index pages.}
                            {--parent-model-class= : The Parent model class.}
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new resource controller.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Controller';
    protected $stubDirectory;

    protected $complexCRUD;
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        $this->stubDirectory = config('crudgenerator.custom_template') ? config('crudgenerator.path') : __DIR__ . '/../stubs/';
        return $this->stubDirectory . '/controller.stub';

    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string $rootNamespace
     *
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Http\Controllers';
    }

    /**
     * Build the model class with the given name.
     * This is called from Illuminate\Console\GeneratorCommand::fire();
     * This class is overwritting the one in Illumuninate\Console\
     *
     * @param  string  $fullNameSpaceName
     *
     * @return string
     */
    protected function buildClass($fullNameSpaceName)
    {
        // Get the template for the controller
        $stub = $this->files->get($this->getStub());

        $viewPath            = $this->option('view-path') ? $this->option('view-path') . '.' : ''; //this is the view path; the end path of this is the 'resoureces/views/...' then if the CRUD has a routePath
        $crudName            = strtolower($this->option('crud-name')); //Class name and CRUD
        $crudNameSingular    = str_singular($crudName);
        $modelName           = $this->option('model-name'); //class name of the model to call
        $modelNamespace      = $this->option('model-namespace'); //namespace of the MODEL this is going to be used at the beginning of the stub
        $routePath           = ($this->option('route-path')) ? $this->option('route-path') . '/' : '';
        $perPage             = intval($this->option('pagination'));
        $viewContainerFolder = snake_case($this->option('crud-name'), '-'); //this is not really a name of the file but the folder in which the create, edit, show, index live.
        $fields              = $this->option('fields');
        $validations         = rtrim($this->option('validations'), ';');

        // Parse the CODE
        $this->ProcessParentCode($stub);

        $validationRules = '';
        if (trim($validations) != '') {
            $validationRules = "\$this->validate(\$request, [";

            $rules = explode(';', $validations);
            foreach ($rules as $v) {
                if (trim($v) == '') {
                    continue;
                }

                // extract field name and args
                $parts     = explode('#', $v);
                $fieldName = trim($parts[0]);
                $rules     = trim($parts[1]);
                $validationRules .= "\n\t\t\t'$fieldName' => '$rules',";
            }

            $validationRules = substr($validationRules, 0, -1); // lose the last comma
            $validationRules .= "\n\t\t]);";
        }

        $snippet = <<<EOD
if (\$request->hasFile('{{fieldName}}')) {
    \$uploadPath = public_path('/uploads/');

    \$extension = \$request->file('{{fieldName}}')->getClientOriginalExtension();
    \$fileName = rand(11111, 99999) . '.' . \$extension;

    \$request->file('{{fieldName}}')->move(\$uploadPath, \$fileName);
    \$requestData['{{fieldName}}'] = \$fileName;
}
EOD;

        $fieldsArray = explode(';', $fields);
        $fileSnippet = '';

        if ($fields) {
            $x = 0;
            foreach ($fieldsArray as $item) {
                $itemArray = explode('#', $item);

                if (trim($itemArray[1]) == 'file') {
                    $fileSnippet .= "\n\n" . str_replace('{{fieldName}}', trim($itemArray[0]), $snippet) . "\n";
                }
            }
        }

        return $this->replaceNamespace($stub, $fullNameSpaceName) //called in Illuminiate\Console\GeneratorCommand
            ->replaceViewPath($stub, $viewPath) //Called in this class
            ->replaceviewContainerFolder($stub, $viewContainerFolder) //Called in this class
            ->replaceCrudName($stub, $crudName) //Called in this class
            ->replaceCrudNameSingular($stub, $crudNameSingular) //Called in this class
            ->replaceModelName($stub, $modelName) //Called in this class
            ->replaceModelNamespace($stub, $modelNamespace) //Called in this class
            ->replaceroutePath($stub, $routePath) //Called in this class
            ->replaceValidationRules($stub, $validationRules) //Called in this class
            ->replacePaginationNumber($stub, $perPage) //Called in this class
            ->replaceFileSnippet($stub, $fileSnippet) //Called in this class
            ->replaceClass($stub, $fullNameSpaceName); //called in Illuminiate\Console\GeneratorCommand
    }

    /**
     * This calls the CRUD complex class
     *
     * @return void
     * @author
     **/
    protected function ProcessParentCode($stub)
    {
        $CRUDComplex = new \Appzcoder\CrudGenerator\shared\CRUDcomplexClass();

        $parentModel = ($this->option('parent-model-class') != null) ? $this->option('parent-model-class') : null;

        return $CRUDComplex->ProcessControllerCreateStub($stub, $parentModel);

    }

    /**
     * Replace the viewContainerFolder fo the given stub.
     *
     * @param string $stub
     * @param string $viewContainerFolder
     *
     * @return $this
     */
    protected function replaceviewContainerFolder(&$stub, $viewContainerFolder)
    {
        $stub = str_replace(
            '{{viewContainerFolder}}', $viewContainerFolder, $stub
        );

        return $this;
    }

    /**
     * Replace the viewPath for the given stub.
     *
     * @param  string  $stub
     * @param  string  $viewPath
     *
     * @return $this
     */
    protected function replaceViewPath(&$stub, $viewPath)
    {
        $stub = str_replace(
            '{{viewPath}}', $viewPath, $stub
        );

        return $this;
    }

    /**
     * Replace the crudName for the given stub.
     *
     * @param  string  $stub
     * @param  string  $crudName
     *
     * @return $this
     */
    protected function replaceCrudName(&$stub, $crudName)
    {
        $stub = str_replace(
            '{{crudName}}', $crudName, $stub
        );

        return $this;
    }

    /**
     * Replace the crudNameSingular for the given stub.
     *
     * @param  string  $stub
     * @param  string  $crudNameSingular
     *
     * @return $this
     */
    protected function replaceCrudNameSingular(&$stub, $crudNameSingular)
    {
        $stub = str_replace(
            '{{crudNameSingular}}', $crudNameSingular, $stub
        );

        return $this;
    }

    /**
     * Replace the modelName for the given stub.
     *
     * @param  string  $stub
     * @param  string  $modelName
     *
     * @return $this
     */
    protected function replaceModelName(&$stub, $modelName)
    {
        $stub = str_replace(
            '{{modelName}}', $modelName, $stub
        );

        return $this;
    }

    /**
     * Replace the modelName for the given stub.
     *
     * @param  string  $stub
     * @param  string  $modelName
     *
     * @return $this
     */
    protected function replaceModelNamespace(&$stub, $modelNamespace)
    {
        $stub = str_replace(
            '{{modelNamespace}}', $modelNamespace, $stub
        );

        return $this;
    }

    /**
     * Replace the routePath for the given stub.
     *
     * @param  string  $stub
     * @param  string  $routePath
     *
     * @return $this
     */
    protected function replaceroutePath(&$stub, $routePath)
    {
        $stub = str_replace(
            '{{routePath}}', $routePath, $stub
        );

        return $this;
    }

    /**
     * Replace the validationRules for the given stub.
     *
     * @param  string  $stub
     * @param  string  $validationRules
     *
     * @return $this
     */
    protected function replaceValidationRules(&$stub, $validationRules)
    {
        $stub = str_replace(
            '{{validationRules}}', $validationRules, $stub
        );

        return $this;
    }

    /**
     * Replace the pagination placeholder for the given stub
     *
     * @param $stub
     * @param $perPage
     *
     * @return $this
     */
    protected function replacePaginationNumber(&$stub, $perPage)
    {
        $stub = str_replace(
            '{{pagination}}', $perPage, $stub
        );

        return $this;
    }

    /**
     * Replace the file snippet for the given stub
     *
     * @param $stub
     * @param $fileSnippet
     *
     * @return $this
     */
    protected function replaceFileSnippet(&$stub, $fileSnippet)
    {
        $stub = str_replace(
            '{{fileSnippet}}', $fileSnippet, $stub
        );

        return $this;
    }
}
