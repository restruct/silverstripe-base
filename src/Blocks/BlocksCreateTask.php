<?php

namespace LeKoala\Base\Dev;

use Exception;
use LeKoala\Base\Blocks\Block;
use LeKoala\Base\Dev\BuildTask;
use SilverStripe\Control\Director;
use LeKoala\Base\Theme\KnowsThemeDir;
use SilverStripe\Core\Injector\Injector;

/**
 * Assist in building blocks for your website
 *
 * @author lekoala
 */
class BlocksCreateTask extends BuildTask
{
    use KnowsThemeDir;

    protected $title = "Create Blocks";
    protected $description = 'Create block classes and styles based on your templates.';
    private static $segment = 'BlocksCreateTask';

    public function init()
    {
        $request = $this->getRequest();

        $themeBlocksPath = Director::baseFolder() . DIRECTORY_SEPARATOR . $this->getThemeDir() . '/templates/Blocks';
        $mysiteBlocksPath = Director::baseFolder() . DIRECTORY_SEPARATOR . project() . '/templates/Blocks';

        $classes = Block::listTemplates();

        $files = [];
        $files = array_merge($files, glob($themeBlocksPath . '/*.ss'));
        $files = array_merge($files, glob($mysiteBlocksPath . '/*.ss'));

        if (empty($files)) {
            $this->message("No blocks found");
            $this->message("Please make sure you have blocks in $themeBlocksPath or in $mysiteBlocksPath");
        }

        $classCreated = false;
        $allBlocks = [];
        $scssDir = dirname($this->getScssFolder(true));

        foreach ($files as $file) {
            $content = file_get_contents($file);

            // Check for lost $Content variable
            if (strpos($content, '$Content') !== false) {
                throw new Exception("Blocks cannot nest Content");
            }

            $result = $this->createBlockClass($file, $classes);
            if ($result) {
                $classCreated = true;
            }
            if (is_dir($scssDir)) {
                $result = $this->createStyles($file);
            }
            $allBlocks[] = pathinfo($file, PATHINFO_FILENAME);
        }


        if (is_dir($scssDir)) {
            $this->message("Refresh all styles");
            $scssFile = $scssDir . '/_blocks.scss';
            $data = '/* This file is automatically generated */' . "\n";
            foreach ($allBlocks as $blockName) {
                $data .= '@import "blocks/' . $blockName . '";' . "\n";
            }
            file_put_contents($scssFile, $data);
        }

        // A new class was added, regenerate the manifest
        if ($classCreated) {
            $this->regenerateClassManifest();
        }
    }

    /**
     * @param boolean $createBase
     * @return string
     */
    protected function getScssFolder($createBase = false)
    {
        $base =  Director::baseFolder() . DIRECTORY_SEPARATOR . $this->getThemeDir() . '/sass';
        // We have a sass dir but no blocks folder
        if (is_dir($base) && !is_dir($base . '/blocks')) {
            mkdir($base . '/blocks', 0755);
        }
        return $base . '/blocks';
    }

    protected function createStyles($file)
    {
        $name = pathinfo($file, PATHINFO_FILENAME);
        $scssBlocksPath = $this->getScssFolder();

        $scssFile = $scssBlocksPath . '/_' . $name . '.scss';
        if (is_file($scssFile)) {
            $this->message("Skip styles for $name");
            return false;
        }

        $this->message("Creating styles for $name", "created");

        $data = <<<SCSS
.Block-$name {

}
SCSS;
        file_put_contents($scssFile, $data);

        return true;
    }

    protected function createBlockClass($file, $classes)
    {
        $name = pathinfo($file, PATHINFO_FILENAME);
        if (isset($classes[$name])) {
            $this->message("Skip block $name");
            return false;
        }
        $this->message("Creating block $name", "created");

        $baseFolder = 'mysite/code';
        if (project() == 'app') {
            $baseFolder = 'app/src';
        }
        $mysite = Director::baseFolder() . DIRECTORY_SEPARATOR . $baseFolder . '/Blocks';
        if (!is_dir($mysite)) {
            mkdir($mysite);
        }

        $filename = $mysite . DIRECTORY_SEPARATOR . $name . '.php';

        $data = <<<PHP
<?php

use LeKoala\Base\Blocks\BaseBlock;
use LeKoala\Base\Blocks\BlockFieldList;

/**
 * $name block
 */
class $name extends BaseBlock
{
    public function updateFields(BlockFieldList \$fields)
    {
        //\$fields->removeByName('Image');
        \$fields->addText('Title');
    }

    /**
     * Extra data to feed to the template
     * @return array
     */
    public function ExtraData()
    {
        return [];
    }

    public function Collection()
    {
        return false;
    }

    public function SharedCollection()
    {
        return false;
    }
}

PHP;
        \file_put_contents($filename, $data);

        return true;
    }
}
