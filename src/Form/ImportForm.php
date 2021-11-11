<?php

/**
 * @file
 * Contains \Drupal\articles_csv_importer\Form\ImportForm.
 */

namespace Drupal\articles_csv_importer\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormInterface;

class ImportForm extends FormBase {
    
    /**
    * {@inheritdoc}
    */
    public function getFormId() {
        return 'example_import_form';
    }

    /**
    * {@inheritdoc}
    */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form['description'] = array(
            '#markup' => '<p>Use this form to upload a CSV file of Data</p>',
        );

        $form['import_csv'] = array(
            '#type' => 'managed_file',
            '#title' => t('Upload file here'),
            '#upload_location' => 'public://importcsv/',
            '#default_value' => '',
            '#upload_validators' => array("file_validate_extensions" => array("csv")),
            '#states' => array(
                'visible' => array(
                    ':input[name="File_type"]' => array('value' => t('Upload Your File')),
                ),
            ),
        );

        $form['actions']['#type'] = 'actions';

        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Upload CSV'),
            '#button_type' => 'primary',
        );

        return $form;
      }

    /**
    * {@inheritdoc}
    */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        //Fetch the array of the file stored temporarily in database 
        $csv_file = $form_state->getValue('import_csv');

        // Load the object of the file by it's fid
        $file = File::load( $csv_file[0]);

        //Set the status flag permanent of the file object
        $file->setPermanent();

        // Save the file in database
        $file->save();

        $data = $this->csvtoarray($file->getFileUri(), ',');
        foreach($data as $row) {
            $operations[] = ['\Drupal\articles_csv_importer\Form\ImportForm::addImportContentItem', [$row]];
        }

        $batch = array(
            'title' => t('Importing Data...'),
            'operations' => $operations,
            'init_message' => t('Import is starting.'),
            'finished' => '\Drupal\articles_csv_importer\Form\ImportForm::addImportContentItemCallback',
        );
        batch_set($batch);
    }

    public function csvtoarray($filename='', $delimiter){
        if(!file_exists($filename) || !is_readable($filename)) return FALSE;
        $header = NULL;
        $data = array();

        if (($handle = fopen($filename, 'r')) !== FALSE) {
            while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
            {
                if(!$header) {
                    $header = $row;
                } else {
                    $data[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }
        return $data;
    }

    public static function addImportContentItem($item, &$context) {
        $context['sandbox']['current_item'] = $item;
        $message = 'Creating ' . $item['title'];
        $results = array();
        self::create_node($item);
        $context['message'] = $message;
        $context['results'] = $item;
    }

    public function addImportContentItemCallback($success, $results, $operations) {
        //The 'success' parameter means no fatal PHP errors were detected. All
        //other error management should be handled using 'results'.
        if ($success) {
            $message = \Drupal::translation()->formalPlural(
            count($results),
            'One item processed.', '@count items processed.'
            );
        } else {
        $message = t('Finished with an error.');
        }   
        \Drupal::messenger()->addMessage($message);
    }

    // Node creation.
    public static function create_node($item) { 
        //Pravljenje fajla za sliku.
        $data = file_get_contents($item['Image']);
        $file = file_save_data($data, 'public://sample.png', \Drupal\Core\File\FileSystemInterface::EXISTS_RENAME);

        $nodes = [
            'type' => 'articles_about_programming',
            'title' => $item['Title'],
            'body' => $item['Description'],
            'field_myimage' => [
                'target_id' => $file->id(),
                'alt' => 'Sample',
                'title' => 'Sample File',
            ],
            'field_link_to_website' => [
                'uri' => 'https://www.' . $item['Link to website'],
                'title' => 'some link title',
                'options' => ['target' => "_blank"],
            ],
        ];
        $node=Node::create($nodes);
        $node->setPublished(TRUE);
        $node->save();
    }
}
