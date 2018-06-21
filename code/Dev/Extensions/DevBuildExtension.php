<?php

namespace LeKoala\Base\Dev\Extensions;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Control\Director;
use LeKoala\Base\Helpers\ClassHelper;
use SilverStripe\ORM\DB;
use LeKoala\Base\Extensions\BaseFileExtension;
use LeKoala\ExcelImportExport\ExcelBulkLoader;

class DevBuildExtension extends Extension
{
    public function beforeCallActionHandler()
    {
        $renameColumns = $this->owner->getRequest()->getVar('renameColumns');
        if ($renameColumns) {
            $this->displayMessage("<div class='build'><p><b>Renaming columns</b></p><ul>\n\n");
            $this->renameColumns();
            $this->displayMessage("</ul>\n<p><b>Columns renamed!</b></p></div>");
        }

        $truncateSiteTree = $this->owner->getRequest()->getVar('truncateSiteTree');
        if ($truncateSiteTree) {
            $this->displayMessage("<div class='build'><p><b>Truncating SiteTree</b></p><ul>\n\n");
            $this->truncateSiteTree();
            $this->displayMessage("</ul>\n<p><b>SiteTree truncated!</b></p></div>");
        }
    }

    protected function truncateSiteTree()
    {
        if (!Director::isDev()) {
            throw new Exception("Only available in dev mode");
        }

        $sql = <<<SQL
        TRUNCATE TABLE ErrorPage;
        TRUNCATE TABLE ErrorPage_Live;
        TRUNCATE TABLE ErrorPage_Versions;
        TRUNCATE TABLE SiteTree;
        TRUNCATE TABLE SiteTree_CrossSubsiteLinkTracking;
        TRUNCATE TABLE SiteTree_EditorGroups;
        TRUNCATE TABLE SiteTree_ImageTracking;
        TRUNCATE TABLE SiteTree_LinkTracking;
        TRUNCATE TABLE SiteTree_Live;
        TRUNCATE TABLE SiteTree_Versions;
        TRUNCATE TABLE SiteTree_ViewerGroups;
SQL;
        DB::query($sql);
        $this->displayMessage($sql);
    }

    /**
     * Loop on all DataObjects and look for rename_columns property
     *
     * It will rename old columns from old_value => new_value
     *
     * @return void
     */
    protected function renameColumns()
    {
        $classes = $this->getDataObjects();

        foreach ($classes as $class) {
            if (!property_exists($class, 'rename_columns')) {
                continue;
            }

            $fields = $class::$rename_columns;

            $schema = DataObject::getSchema();
            $tableName = $schema->baseDataTable($class);

            $dbSchema = DB::get_schema();
            foreach ($fields as $oldName => $newName) {
                if ($dbSchema->hasField($tableName, $oldName)) {
                    $this->displayMessage("<li>Renaming $oldName to $newName in $tableName</li>");
                    $dbSchema->renameField($tableName, $oldName, $newName);
                } else {
                    $this->displayMessage("<li>$oldName is already renamed to $newName in $tableName</li>");
                }
            }
        }
    }

    public function afterCallActionHandler()
    {
        $envIsAllowed = Director::isDev();
        $skipGeneration = $this->owner->getRequest()->getVar('skipgeneration');

        if ($skipGeneration || !$envIsAllowed) {
            return;
        }

        // $this->displayMessage("<div class='build'><p><b>Generating ide helpers</b></p><ul>\n\n");
        // $this->generateQueryTraits();
        // $this->generateRepository();
        // $this->displayMessage("</ul>\n<p><b>Generating ide helpers finished!</b></p></div>");
    }

    protected function generateQueryTraits()
    {
        $classes = $this->getDataObjects();
        $pages = ClassInfo::subclassesFor('Page');

        $classesWithoutPages = array_diff_key($classes, $pages);

        foreach ($classesWithoutPages as $lcClass => $class) {
            $module = ClassHelper::findModuleForClass($class);

            $moduleName = $module->getName();
            if ($moduleName != 'mysite') {
                continue;
            }

            $traitDir = $module->getPath() . '/code/traits';
            if (!is_dir($traitDir)) {
                mkdir($traitDir);
            }
            $className = ClassHelper::getClassWithoutNamespace($class);
            $traitName = $className . 'Queries';
            $file = ClassHelper::findFileForClass($class);
            $content = file_get_contents($file);

            // Do we need to insert the trait usage?
            if (strpos($content, "use $traitName;") === false) {
                // properly insert after class opens
                $newContent = str_replace("extends DataObject {", "extends DataObject {\n    use $traitName;\n", $content);
                //TODO: insert if namespaced
                // $content = str_replace('use SilverStripe\ORM\DataObject;', "use SilverStripe\ORM\DataObject;\nuse \\$traitName;", $content);
                if ($newContent != $content) {
                    file_put_contents($file, $newContent);
                    $this->displayMessage("<li>Trait usage added to $className</li>");
                } else {
                    $this->displayMessage("<li style=\"color:red\">Could not add trait to $className</li>");
                }
            }

            // Generate trait
            $code = <<<CODE
<?php
// phpcs:ignoreFile -- this is a generated file

trait $traitName
{
    /**
     * @params int|string|array \$idOrWhere numeric ID or where clause (as string or array)
     * @return $class
     */
    public static function findOne(\$idOrWhere)
    {
        return \LeKoala\Base\ORM\QueryHelper::findOne(\\$class::class, \$idOrWhere);
    }

    /**
     * @params array \$filters
     * @return {$class}[]
     */
    public static function find(\$filters = null) {
        return \LeKoala\Base\ORM\QueryHelper::find(\\$class::class, \$filters);
    }
CODE;

            $code .= "\n}";

            file_put_contents($traitDir . '/' . $traitName . '.php', $code);
            $this->displayMessage("<li>Trait $traitName generated</li>");
        }
    }

    /**
     * @return array
     */
    protected function getDataObjects()
    {
        $classes = ClassInfo::subclassesFor(DataObject::class);
        array_shift($classes); // remove dataobject
        return $classes;
    }

    /**
     * Generate the repository class
     *
     * @return void
     */
    protected function generateRepository()
    {
        $classes = $this->getDataObjects();

        $code = <<<CODE
<?php
// phpcs:ignoreFile -- this is a generated file
class Repository {
CODE;
        foreach ($classes as $lcClass => $class) {
            $classWithoutNS = ClassHelper::getClassWithoutNamespace($class);

            $method = <<<CODE
    /**
     * @params int|string|array \$idOrWhere numeric ID or where clause (as string or array)
     * @return $class
     */
    public static function $classWithoutNS(\$idOrWhere) {
        return \LeKoala\Base\ORM\QueryHelper::findOne(\\$class::class, \$idOrWhere);
    }

    /**
     * @params array \$filters
     * @return {$class}[]
     */
    public static function {$classWithoutNS}List(\$filters = null) {
        return \LeKoala\Base\ORM\QueryHelper::find(\\$class::class, \$filters);
    }

CODE;
            $code .= $method;
        }

        $code .= "\n}";

        $dest = Director::baseFolder() . '/mysite/code/Repository.php';
        file_put_contents($dest, $code);

        $this->displayMessage("<li>Repository class generated</li>");
    }

    /**
     * @param $message
     */
    protected function displayMessage($message)
    {
        echo Director::is_cli() ? "\n" . $message . "\n\n" : "$message";
    }
}
