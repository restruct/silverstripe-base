<?php
namespace LeKoala\Base\Extensions;

use SilverStripe\ORM\DB;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Versioned\Versioned;
use LeKoala\Base\Forms\BuildableFieldList;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;

/**
 * Improve DataObjects
 *
 * - cascade_delete relations should not be a relation, but a record editor
 * - summary fields should include subsite extra fields
 * - after delete, cleanup tables
 *
 */
class BaseDataObjectExtension extends DataExtension
{
    public function updateCMSFields(FieldList $fields)
    {
        $fields = BuildableFieldList::fromFieldList($fields);
        $cascade_delete = $this->owner->config()->cascade_deletes;
        // Anything that is deleted in cascade should not be a relation (most of the time!)
        $this->turnRelationsIntoRecordEditor($cascade_delete, $fields);

        // extraFields are wanted!
        $extraFields = $this->owner->config()->many_many_extraFields;
        $this->expandGridFieldSummary($extraFields, $fields);
    }

    /**
     * @return bool
     */
    protected function isVersioned()
    {
        return $this->owner->hasExtension(Versioned::class);
    }

    /**
     * @param string $class
     * @return bool
     */
    protected function isAssetClass($class)
    {
        return $class === Image::class || $class === File::class;
    }


    public function onAfterDelete()
    {
        $this->cleanupManyManyTables();
    }

    public function onAfterWrite()
    {
        $this->publishOwnAssets();
    }

    protected function publishOwnAssets()
    {
        if ($this->isVersioned()) {
            return;
        }

        $owns = $this->owner->config()->owns;
        foreach ($owns as $componentName => $componentClass) {
            if ($this->isAssetClass($componentClass)) {
                $component = $this->owner->getComponent($componentName);
                if ($component->isInDB() && !$component->isPublished()) {
                    $component->publishSingle();
                }
            }
        }
    }

    protected function expandGridFieldSummary($arr, BuildableFieldList $fields)
    {
        if (!$arr) {
            return;
        }
        foreach ($arr as $class => $data) {
            $gridfield = $fields->getGridField($class);
            if (!$gridfield) {
                continue;
            }
            $config = $gridfield->getConfig();

            $GridFieldDataColumns = $config->getComponentByType(GridFieldDataColumns::class);
            $display = $GridFieldDataColumns->getDisplayFields($gridfield);
            foreach ($data as $k => $v) {
                $display[$k] = $k;
            }
            $GridFieldDataColumns->setDisplayFields($display);
        }
    }

    /**
     * @param array $arr List of relations
     * @param BuildableFieldList $fields
     * @return void
     */
    protected function turnRelationsIntoRecordEditor($arr, BuildableFieldList $fields)
    {
        if (!$arr) {
            return;
        }
        $ownerClass = get_class($this->owner);
        $allSubclasses = ClassInfo::ancestry($ownerClass, true);

        foreach ($arr as $class) {
            $sanitisedClass = str_replace('\\', '-', $class);
            $gridfield = $fields->getGridField($sanitisedClass);
            if (!$gridfield) {
                continue;
            }
            $config = $gridfield->getConfig();
            $config->removeComponentsByType(GridFieldAddExistingAutocompleter::class);

            $deleteAction = $config->getComponentByType(GridFieldDeleteAction::class);
            if ($deleteAction) {
                $config->removeComponentsByType(GridFieldDeleteAction::class);
                $config->addComponent(new GridFieldDeleteAction());
            }

            $dataColumns = $config->getComponentByType(GridFieldDataColumns::class);
            if ($dataColumns) {
                $displayFields = $dataColumns->getDisplayFields($gridfield);
                $newDisplayFields = [];
                // Strip any columns referencing current or parent class
                foreach ($displayFields as $k => $v) {
                    foreach ($allSubclasses as $lcClass => $class) {
                        if (strpos($k, $class . '.') === 0) {
                            continue 2;
                        }
                    }
                    $newDisplayFields[$k] = $v;
                }
                $dataColumns->setDisplayFields($newDisplayFields);
            }
        }
    }

    /**
     * SilverStripe does not delete by default records in many_many table
     * leaving many orphans rows
     *
     * Run this to avoid the problem
     *
     * @return void
     */
    protected function cleanupManyManyTables()
    {
        // We should not cleanup tables on versioned items because they can be restored
        if ($this->isVersioned()) {
            return;
        }
        $many_many = $this->owner->manyMany();
        foreach ($many_many as $relation => $type) {
            $manyManyComponents = $this->owner->getManyManyComponents($relation);
            $table = $manyManyComponents->getJoinTable();
            $key = $manyManyComponents->getForeignKey();
            $id = $this->owner->ID;
            $sql = "DELETE FROM $table WHERE $key = $id";
            DB::query($sql);
        }
    }
}
