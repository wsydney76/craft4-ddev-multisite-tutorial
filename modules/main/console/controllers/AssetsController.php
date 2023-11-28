<?php

namespace modules\main\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use GuzzleHttp\Exception\GuzzleException;
use yii\console\ExitCode;
use function count;

class AssetsController extends Controller
{

    /**
     * Creates images transforms by requesting each entry
     *
     * php craft main/assets/create-transforms
     *
     * @throws GuzzleException
     */
    public function actionCreateTransforms(): int
    {
        if ($this->interactive && !$this->confirm('Retrieve each page to create missing image sizes? This will take some time.', true)) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Retrieving pages" . PHP_EOL);

        $client = Craft::createGuzzleClient();

        $entries = Entry::find()
            ->uri(':notempty:')
            ->site('*') // uncomment this line if this is a multi-site install
            // ->unique() // uncomment this line if there are no site specific images in a multisite install
            ->orderBy('id')
            ->all();

        $c = count($entries);
        $i = 0;
        $errors = 0;

        foreach ($entries as $entry) {
            $i++;
            $this->stdout("[{$i}/{$c}] Id: {$entry->id} {$entry->title} ({$entry->site->name})... ");

            try {
                $result = $client->get($entry->getUrl());
                $this->stdout((string)$result->getStatusCode());
            } catch (\Exception $exception) {
                if ($exception->getCode() === 400) {
                    // Errors can occur if required params are not provided
                    $this->stdout("Error 400, missing params");
                } elseif ($exception->getCode() !== 403) {
                    // 403 errors can occur if the page is protected by a login, or is just used for cp previews
                    $errors++;
                    $this->stdout("Error {$exception->getMessage()}");
                }
            }

            $this->stdout(PHP_EOL);
        }


        $this->stdout("Done with $errors error(s)" . PHP_EOL);

        return ExitCode::OK;
    }
}