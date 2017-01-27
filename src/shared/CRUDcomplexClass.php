<?php
namespace Appzcoder\CrudGenerator\shared;

use File;

/**
 * This is a shared class
 */
class CRUDcomplexClass
{

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
     * View Directory Path.
     *
     * @var string
     */
    protected $viewDirectoryPath;
    protected $defaultColumnsToShow;
    protected $formFieldsHtml;
    protected $formHeadingHtml;
    protected $formBodyHtml;
    protected $formBodyHtmlForShowView;

    public function __construct()
    {
        $this->viewDirectoryPath = config('crudgenerator.custom_template') ? config('crudgenerator.path') : __DIR__ . '/../stubs/';

        if (config('crudgenerator.view_columns_number')) {
            $this->defaultColumnsToShow = config('crudgenerator.view_columns_number');
        }
    }
    /**
     * Processes the Json fields to generate the index, forms, template.
     *  This can be called outside from the general Crud Command
     * @return html
     * @author bramburn (icelabz.co.uk)
     **/
    public function ProcessComplexJsonFields($crudName, $entryFields, $info)
    {
        $info_check = [
            'routePath',
            'modelName',
            'primaryKey',
            'viewPath',
            'viewContainerFolder',

        ];

        foreach ($info_check as $key) {
            if (isset($info[$key])) {
                continue;
            } else {
                return false;
            }

        }

        // cleanup for multiple process
        $this->formFieldsHtml          = '';
        $this->formHeadingHtml         = '';
        $this->formBodyHtml            = '';
        $this->formBodyHtmlForShowView = '';

        // Create Directory
        $path = $this->CreateDirectory($info['viewPath'], $info['viewContainerFolder']);

        // Process Fields
        foreach ($entryFields as $value) {

            if ($value['type'] == 'select' && isset($value['options'])) {

                $optionsArray = $value['options'];

                $commaSeparetedString = implode("', '", $optionsArray);
                $options              = "['" . $commaSeparetedString . "']";

                $value['options'] = $options;
            }
            if (!isset($value['showform']) or $value['showform'] != 'no') {
                $this->formFieldsHtml .= $this->createField($value);
            }

            // by default if showInIndex is not defined then we show or if it is defined and isn't 'no' show
            if (!isset($value['showInIndex']) or $value['showInIndex'] != 'no') {

                $field = $value['name'];
                $label = ucwords(str_replace('_', ' ', $field));
                // if ($this->option('localize') == 'yes') {
                //     $label = '{{ trans(\'' . $crudName . '.' . $field . '\') }}';
                // }
                $this->formHeadingHtml .= '<th> ' . $label . ' </th>' . "\n";
                $this->formBodyHtml .= '<td>{{ $item->' . $field . ' }}</td>' . "\n";

            }

            // Still show information in ShowView
            $this->formBodyHtmlForShowView .= '<tr><th> ' . $label . ' </th><td> {{ $%%crudNameSingular%%->' . $field . ' }} </td></tr>';

        }

        // Process index template

        $viewTemplateDir = isset($info['viewPath']) ? $info['viewPath'] . '.' . $info['viewContainerFolder'] : $info['viewContainerFolder'];
        $strLowerName    = strtolower($crudName);
        $templateData    = [
            '%%crudName%%'            => $strLowerName, //k
            '%%crudNameCap%%'         => ucwords($strLowerName), //ok
            '%%crudNameSingular%%'    => str_singular($strLowerName), //ok
            '%%ParentModelClass%%'    => $info['parent_modelClass'],
            '%%modelName%%'           => $info['modelName'],
            '%%primaryKey%%'          => $info['primaryKey'],
            '%%routePath%%'           => $info['routePath'],
            '%%viewContainerFolder%%' => $info['viewContainerFolder'],
            '%%viewTemplateDir%%'     => $viewTemplateDir,

        ];

        // double fried chips
        $templateData['%%formFieldsHtml%%']          = $this->ParseHTML($this->formFieldsHtml, $templateData); //generated here
        $templateData['%%formBodyHtml%%']            = $this->ParseHTML($this->formBodyHtml, $templateData); //generated here
        $templateData['%%formBodyHtmlForShowView%%'] = $this->ParseHTML($this->formBodyHtmlForShowView, $templateData); //generated here
        $templateData['%%formHeadingHtml%%']         = $this->ParseHTML($this->formHeadingHtml, $templateData); //generated here

        // DTEST

        // $path .= "demo/";

        $indexFile    = $this->viewDirectoryPath . 'index.blade.stub';
        $newIndexFile = $path . 'index.blade.php';

        if (!File::copy($indexFile, $newIndexFile)) {
            echo "failed to copy $indexFile...\n";
        } else {
            $this->ProcessStubFile($newIndexFile, $templateData);
        }

        $formFile    = $this->viewDirectoryPath . 'form.blade.stub';
        $newformFile = $path . 'form.blade.php';

        if (!File::copy($formFile, $newformFile)) {
            echo "failed to copy $formFile...\n";
        } else {
            $this->ProcessStubFile($newformFile, $templateData);
        }

        $createFile    = $this->viewDirectoryPath . 'create.blade.stub';
        $newcreateFile = $path . 'create.blade.php';

        if (!File::copy($createFile, $newcreateFile)) {
            echo "failed to copy $createFile...\n";
        } else {
            $this->ProcessStubFile($newcreateFile, $templateData);
        }

        $editFile    = $this->viewDirectoryPath . 'edit.blade.stub';
        $neweditFile = $path . 'edit.blade.php';

        if (!File::copy($editFile, $neweditFile)) {
            echo "failed to copy $editFile...\n";
        } else {
            $this->ProcessStubFile($neweditFile, $templateData);
        }

        $showFile    = $this->viewDirectoryPath . 'show.blade.stub';
        $newshowFile = $path . 'show.blade.php';

        if (!File::copy($showFile, $newshowFile)) {
            echo "failed to copy $showFile...\n";
        } else {
            $this->ProcessStubFile($newshowFile, $templateData);
        }

    }

    /**
     * Processes and create the code for the Create function of the controller class
     *
     * @return html/php
     * @author bramburn (icelabz.co.uk)
     **/
    public function ProcessControllerCreateStub($html, $parentNamespace = null)
    {
        if ($parentNamespace != null) {
            $namespace      = "use " . $parentNamespace . " as ParentModel;";
            $controllerStub = $this->viewDirectoryPath . 'extensions/child.controller.create.stub';
        } else {
            $namespace      = "";
            $controllerStub = $this->viewDirectoryPath . 'extensions/normal.controller.create.stub';
        }
        $templateData['{{controller.create}}']    = $controllerStub; // add controller function
        $templateData['{{parentModelNamespace}}'] = $namespace; // add parent model
        $html                                     = $this->ParseHTML($html, $templateData);

        return $html;

    }

    /**
     * This checks and created directory
     *
     * @return folder
     * @author bramburn (icelabz.co.uk)
     **/
    protected function CreateDirectory($viewPath, $viewContainerFolder)
    {
        $viewDirectory = config('view.paths')[0] . '/';
        if (!is_null($viewPath)) {

            $path = $viewDirectory . $viewPath . '/' . $viewContainerFolder . '/';
        } else {
            $path = $viewDirectory . $viewContainerFolder . '/';
        }

        if (!File::isDirectory($path)) {
            // doesn't exists? good let's make the dir
            File::makeDirectory($path, 0755, true);
        }
        //ends with trailing slash
        return $path;

    }

    /**
     * Replaces the variables in a stub file
     *  allows for multile
     * @return html
     * @author
     **/
    public function ProcessStubFile($stubFileLocation, $templateData, $returnHTML = false)
    {
        // get file content
        $fileContent = File::get($stubFileLocation);

        foreach ($templateData as $key => $value) {
            $fileContent = str_replace($key, $value, $fileContent);
        }

        if ($returnHTML == false) {
            return File::put($stubFileLocation, $fileContent);
        } else {
            // allows for content to be merged in other files
            return $fileContent;
        }
    }

    /**
     * undocumented function
     *
     * @return void
     * @author
     **/
    public function ParseHTML($html, $templateData)
    {

        foreach ($templateData as $key => $value) {
            $html = str_replace($key, $value, $html);
        }

        return $html;

    }

    /**
     * Form field wrapper.
     *
     * @param  string $item
     * @param  string $field
     *
     * @return void
     */
    protected function wrapField($item, $field)
    {
        $formGroup = File::get($this->viewDirectoryPath . 'form-fields/wrap-field.blade.stub');

        $labelText = "'" . ucwords(strtolower(str_replace('_', ' ', $item['name']))) . "'";

        // if ($this->option('localize') == 'yes') {
        //     $labelText = 'trans(\'' . $this->crudName . '.' . $item['name'] . '\')';
        // }

        return sprintf($formGroup, $item['name'], $labelText, $field);
    }

    /**
     * Form field generator.
     *
     * @param  array $item
     *
     * @return string
     */
    protected function createField($item)
    {
        switch ($this->typeLookup[$item['type']]) {
            case 'password':
                return $this->createPasswordField($item);
                break;
            case 'datetime-local':
            case 'time':
                return $this->createInputField($item);
                break;
            case 'radio':
                return $this->createRadioField($item);
                break;
            case 'select':
            case 'enum':
                return $this->createSelectField($item);
                break;
            default: // text
                return $this->createFormField($item);
        }
    }

    /**
     * Create a specific field using the form helper.
     *
     * @param  array $item
     *
     * @return string
     */
    protected function createFormField($item)
    {
        $required = ($item['required'] === true) ? ", 'required' => 'required'" : "";

        $markup = File::get($this->viewDirectoryPath . 'form-fields/form-field.blade.stub');
        $markup = str_replace('%%required%%', $required, $markup);
        $markup = str_replace('%%fieldType%%', $this->typeLookup[$item['type']], $markup);
        $markup = str_replace('%%itemName%%', $item['name'], $markup);

        return $this->wrapField(
            $item,
            $markup
        );
    }

    /**
     * Create a password field using the form helper.
     *
     * @param  array $item
     *
     * @return string
     */
    protected function createPasswordField($item)
    {
        $required = ($item['required'] === true) ? ", 'required' => 'required'" : "";

        $markup = File::get($this->viewDirectoryPath . 'form-fields/password-field.blade.stub');
        $markup = str_replace('%%required%%', $required, $markup);
        $markup = str_replace('%%itemName%%', $item['name'], $markup);

        return $this->wrapField(
            $item,
            $markup
        );
    }

    /**
     * Create a generic input field using the form helper.
     *
     * @param  array $item
     *
     * @return string
     */
    protected function createInputField($item)
    {
        $required = ($item['required'] === true) ? ", 'required' => 'required'" : "";

        $markup = File::get($this->viewDirectoryPath . 'form-fields/input-field.blade.stub');
        $markup = str_replace('%%required%%', $required, $markup);
        $markup = str_replace('%%fieldType%%', $this->typeLookup[$item['type']], $markup);
        $markup = str_replace('%%itemName%%', $item['name'], $markup);

        return $this->wrapField(
            $item,
            $markup
        );
    }

    /**
     * Create a yes/no radio button group using the form helper.
     *
     * @param  array $item
     *
     * @return string
     */
    protected function createRadioField($item)
    {
        $markup = File::get($this->viewDirectoryPath . 'form-fields/radio-field.blade.stub');

        return $this->wrapField($item, sprintf($markup, $item['name']));
    }

    /**
     * Create a select field using the form helper.
     *
     * @param  array $item
     *
     * @return string
     */
    protected function createSelectField($item)
    {
        $required = ($item['required'] === true) ? ", 'required' => 'required'" : "";

        $markup = File::get($this->viewDirectoryPath . 'form-fields/select-field.blade.stub');
        $markup = str_replace('%%required%%', $required, $markup);
        $markup = str_replace('%%options%%', $item['options'], $markup);
        $markup = str_replace('%%itemName%%', $item['name'], $markup);

        return $this->wrapField(
            $item,
            $markup
        );
    }
}
