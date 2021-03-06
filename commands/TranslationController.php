<?php

namespace humhub\modules\translation\commands;

use Yii;
use humhub\modules\translation\Module;

/**
 * Translation MOdule
 *
 * @property Module $module
 * @since 0.5
 */
class TranslationController extends \yii\console\Controller
{

    /**
     * Automatically translates message duplicates
     */
    public function actionHandleDuplicate()
    {

        $translationModule = Yii::$app->getModule('translation');

        foreach ($translationModule->getLanguages() as $language => $title) {
            print "\nHandling Language: " . $language . "\n";

            /**
             * Collect all Translated Messages
             */
            $allTranslatedMessages = array();
            $messageCount = 0;
            foreach ($translationModule->getModuleIds() as $moduleClass => $title) {
                foreach ($translationModule->getFiles($moduleClass, $language) as $file) {
                    $fileName = $translationModule->getTranslationFile($moduleClass, $language, $file);
                    $messages = $translationModule->getTranslationMessages($fileName);
                    $messageCount += count($messages);

                    foreach ($messages as $original => $translated) {
                        $translated = str_replace("@@", "", $translated);
                        if ($translated != "") {
                            $allTranslatedMessages[$original] = $translated;
                        }
                    }
                }
            }


            // Load Message Archive
            $archiveFile = Yii::getAlias('@humhub/messages/' . $language . '/archive.json');
            if (is_file($archiveFile)) {
                $archiveMessages = \yii\helpers\Json::decode(file_get_contents($archiveFile));
                foreach ($archiveMessages as $key => $msg) {
                    if (!isset($allTranslatedMessages[$key]) && !empty($msg[0])) {
                        $allTranslatedMessages[$key] = $msg[0];
                        #print "added: ".$key." - ".$msg[0]." from archive\n";
                    }
                }
            }

            $autoTranslated = 0;
            foreach ($translationModule->getModuleIds() as $moduleClass => $title) {
                foreach ($translationModule->getFiles($moduleClass, $language) as $file => $title) {
                    $fileName = $translationModule->getTranslationFile($moduleClass, $language, $file);
                    $messages = $translationModule->getTranslationMessages($fileName);
                    $messagesChanged = false;

                    foreach ($messages as $original => $translated) {
                        if ($translated == "" && isset($allTranslatedMessages[$original]) && $allTranslatedMessages[$original] != "") {
                            $messages[$original] = $allTranslatedMessages[$original];
                            $autoTranslated++;
                            $messagesChanged = true;
                        }
                    }

                    if ($messagesChanged) {
                        $translationModule->saveTranslationMessages($fileName, $messages);
                    }
                }
            }

            print "\tTotal messages:" . $messageCount . "\n";
            print "\tTranslated:" . count($allTranslatedMessages) . "\n";
            print "\tAuto translated:" . $autoTranslated . "\n";
        }


        print "\n";
    }


    public function actionRenameCategory($moduleId, $oldCategory, $newCategory)
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('translation');

        $basePath = Yii::$app->getModule($moduleId)->getBasePath() . '/messages';

        print "Rename:" . $basePath . "\n";

        foreach ($module->getLanguages() as $lang) {
            $oldFile = $basePath . '/' . $lang . '/' . $oldCategory . '.php';
            $newFile = $basePath . '/' . $lang . '/' . $newCategory . '.php';

            if (!is_file($oldFile)) {
                print "!Skipped: " . $oldFile . "\n\n";
                continue;
            }

            $oldMessages = $module->getTranslationMessages($oldFile);
            
            if (!is_file($newFile)) {
                $newMessages = [];
            } else {
                $newMessages = $module->getTranslationMessages($newFile);
            }

            $module->saveTranslationMessages($newFile,
                array_merge($oldMessages, $newMessages));

            unlink($oldFile);
            
            print "Updated: " . $newFile . "\n";
            print "Deleted: " . $oldFile . "\n\n";
        }


    }

}
