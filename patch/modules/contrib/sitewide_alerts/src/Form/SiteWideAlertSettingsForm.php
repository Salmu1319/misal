<?php

namespace Drupal\sitewide_alerts\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * SiteWide Alert Settings Form.
 *
 * @package Drupal\sitewide_alerts\Form
 */
class SiteWideAlertSettingsForm extends ConfigFormBase {

  /**
   * The cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language.
   *
   * @var string
   */
  protected $language;

  /**
   * SiteWideAlertSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Cache\CacheFactoryInterface $cache_factory
   *   The cache factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManager $language_manager
   *   The language manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    CacheFactoryInterface $cache_factory,
    StateInterface $state,
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManager $language_manager
  ) {
    parent::__construct($config_factory);
    $this->cache = $cache_factory->get('render');
    $this->state = $state;
    $this->entityTypeManager = $entity_type_manager;
    $this->language = $language_manager->getCurrentLanguage()->getId();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('cache_factory'),
      $container->get('state'),
      $container->get('entity_type.manager'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitewide_alerts_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sitewide_alerts.settings'];
  }

  /**
   * Get state configuration.
   *
   * @return array
   *   Return the state config.
   */
  private function getStateConfig() {
    $state_keys = [
      'site_alert_active',
      'site_alert_position',
      'site_alert_merge',
      'site_alert_content_types',
      'site_alert_expiration',
    ];
    return $this->state->getMultiple($state_keys);
  }

  /**
   * Set state configuration data.
   *
   * @param array $data
   *   Set the state config.
   */
  private function setStateConfig(array $data) {
    $this->state->setMultiple($data);
  }

  /**
   * Returns a list of all the content types currently installed.
   *
   * @return array
   *   An array of content types.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getContentTypes() {
    $types = [];
    $contentTypes = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    foreach ($contentTypes as $contentType) {
      $types[$contentType->id()] = $contentType->label();
    }
    return $types;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $state_config = $this->getStateConfig();

    $form['alert'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Alert Settings'),
    ];

    $form['alert']['alert_active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable site alert'),
      '#description' => $this->t('Enable or disable site wide alert message.'),
      '#default_value' => !empty($state_config['site_alert_active']) ? $state_config['site_alert_active'] : FALSE,
    ];

    $positions = [
      'top' => $this->t('Top'),
      'bottom' => $this->t('Bottom'),
    ];
    $form['alert']['alert_position'] = [
      '#type' => 'select',
      '#title' => $this->t('Site alert position'),
      '#options' => $positions,
      '#description' => $this->t('Set position of site alert.'),
      '#default_value' => !empty($state_config['site_alert_position']) ? $state_config['site_alert_position'] : 'top',
      '#states' => [
        'visible' => [
          ':input[name="alert_active"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $expirations = [
      'year' => $this->t('1 Year'),
      'month' => $this->t('1 Month'),
      'week' => $this->t('1 Week'),
      'day' => $this->t('1 Day'),
      'default' => $this->t('Default'),
    ];
    $form['alert']['alert_expiration'] = [
      '#type' => 'select',
      '#title' => $this->t('Site alert cookie expiration'),
      '#options' => $expirations,
      '#description' => $this->t('Set expiration of the site alert cookie.<br>The default value would set the cookie to expire when the browser session ends.'),
      '#default_value' => !empty($state_config['site_alert_expiration']) ? $state_config['site_alert_expiration'] : 'default',
    ];

    $form['alert']['alert_merge'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Merge alerts'),
      '#description' => $this->t('Combines all alerts into one alert bar.'),
      '#default_value' => !empty($state_config['site_alert_merge']) ? $state_config['site_alert_merge'] : FALSE,
    ];

    $form['alert']['alert_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Site alert content types'),
      '#description' => $this->t('Select which content types can be used on alert internal link selection.'),
      '#options' => $this->getContentTypes(),
      '#empty_option' => $this->t('- Select content type(s) -'),
      '#default_value' => !empty($state_config['site_alert_content_types']) ? $state_config['site_alert_content_types'] : ['page'],
      '#required' => TRUE,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Set config.
    $this->setStateConfig([
      'site_alert_active' => $form_state->getValue('alert_active'),
      'site_alert_position' => $form_state->getValue('alert_position'),
      'site_alert_merge' => $form_state->getValue('alert_merge'),
      'site_alert_content_types' => $form_state->getValue('alert_content_types'),
      'site_alert_expiration' => $form_state->getValue('alert_expiration'),
    ]);

    // Invalidate cache tags.
    Cache::invalidateTags(['sitewide_alerts']);
  }

}
