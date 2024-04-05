<?php

namespace Drupal\citybbq_paragraph_scheduling\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;

/**
 * Configuration form for CityBBQ Paragraph Scheduling settings.
 */
class CityBBQParagraphSchedulingConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['citybbq_paragraph_scheduling.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'citybbq_paragraph_scheduling_settings_form';
  }

  /**
   * Builds the form.
   *
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('citybbq_paragraph_scheduling.settings');

    // Fetch all paragraph types to list as options.
    $paragraph_types = \Drupal::service('entity_type.manager')->getStorage('paragraphs_type')->loadMultiple();
    $options = [];
    foreach ($paragraph_types as $type => $info) {
      $options[$type] = $info->label();
    }

    $form['enabled_paragraph_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enable scheduling for the following paragraph types:'),
      '#options' => $options,
      '#default_value' => $config->get('enabled_paragraph_types') ?: [],
      '#description' => $this->t('Select the paragraph types that should have publish and unpublish scheduling.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Form submission handler.
   *
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $enabled_types = array_filter($form_state->getValue('enabled_paragraph_types'));
    $this->config('citybbq_paragraph_scheduling.settings')
      ->set('enabled_paragraph_types', $enabled_types)
      ->save();

    // Update form display settings for each selected paragraph type.
    foreach ($enabled_types as $type) {
      if ($type) {
        $this->enableFieldsForParagraphType($type);
      }
    }
  }

  protected function enableFieldsForParagraphType($paragraph_type) {
    $entity_type = 'paragraph';
    $bundle = $paragraph_type;
    $view_display = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->load("{$entity_type}.{$bundle}.default");

    if (!$view_display) {
      $view_display = \Drupal\Core\Entity\Entity\EntityFormDisplay::create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }

    // Specify the fields to enable
    $fields_to_enable = ['paragraph_item_publish_time', 'paragraph_item_unpublish_time'];

    foreach ($fields_to_enable as $field_name) {
      if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
        continue; // Skip if field does not exist.
      }
      
      // Set the field to be displayed in the form, with example configuration.
      $view_display->setComponent($field_name, [
        'type' => 'datetime_default',
        'weight' => 10,
      ])->save();
    }
  }
}
