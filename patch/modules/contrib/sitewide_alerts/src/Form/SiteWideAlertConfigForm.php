<?php

namespace Drupal\sitewide_alerts\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormBase;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Component\Utility\Random;
use Drupal\domain\DomainNegotiator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Site Wide Alert Config Form.
 *
 * @package Drupal\sitewide_alerts\Form
 */
class SiteWideAlertConfigForm extends FormBase {

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The language.
   *
   * @var string
   */
  protected $language;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The domain negotiator.
   *
   * @var \Drupal\domain\DomainNegotiator
   */
  protected $domainNegotiator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * SiteWideAlertConfigForm constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   * @param \Drupal\Core\Language\LanguageManager $language_manager
   *   The language manager.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler.
   * @param \Drupal\domain\DomainNegotiator $domain_negotiator
   *   The domain negotiator.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    StateInterface $state,
    LanguageManager $language_manager,
    ModuleHandler $module_handler,
    DomainNegotiator $domain_negotiator,
    EntityTypeManager $entity_type_manager
  ) {
    $this->state = $state;
    $this->languageManager = $language_manager;
    $this->language = $this->languageManager->getCurrentLanguage()->getId();
    $this->moduleHandler = $module_handler;
    $this->domainNegotiator = $domain_negotiator;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('language_manager'),
      $container->get('module_handler'),
      $container->get('domain.negotiator'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitewide_alerts_config_form';
  }

  /**
   * Get state configuration.
   *
   * @return array
   *   Return the state config.
   */
  private function getStateConfig() {
    $state_keys = [
      'site_alert.' . $this->language . '.alerts',
      'site_alert_content_types',
    ];
    return $this->state->getMultiple($state_keys);
  }

  /**
   * Set state configuration data.
   *
   * @param array $data
   *   The state config data.
   */
  private function setStateConfig(array $data) {
    $this->state->setMultiple($data);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $state_config = $this->getStateConfig();
    $site_alerts = !empty($state_config['site_alert.' . $this->language . '.alerts']) ? $state_config['site_alert.' . $this->language . '.alerts'] : [];

    $site_alert_content_types = ['page'];
    if (!empty($state_config['site_alert_content_types'])) {
      $site_alert_content_types = $state_config['site_alert_content_types'];
    }

    $num_alerts = $form_state->get('num_alerts');
    if ($num_alerts === NULL) {
      if (!empty($site_alerts)) {
        $num_alerts = count($site_alerts);
      }
      else {
        $num_alerts = 1;
      }
      $form_state->set('num_alerts', $num_alerts);
    }

    $form['#tree'] = TRUE;
    $form['alerts'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Site Alert(s) (%language)', [
        '%language' => $this->languageManager->getCurrentLanguage()->getName(),
      ]),
      '#prefix' => '<div id="site-alerts-wrapper">',
      '#suffix' => '</div>',
    ];

    // Support for domains.
    $domainOptions = [];
    if ($this->moduleHandler->moduleExists('domain')) {
      $domains = $this->entityTypeManager->getStorage('domain')
        ->loadByProperties();
      foreach ($domains as $key => $domain) {
        $domainOptions[$key] = $domain->label();
      }
    }

    $alert_page = NULL;
    for ($i = 1; $i <= $num_alerts; $i++) {
      $form['alerts']['alert'][$i] = [
        '#type' => 'details',
        '#open' => FALSE,
        '#title' => 'Alert #' . $i,
      ];
      if ($i == 1) {
        $form['alerts']['alert'][$i]['#title'] .= ' (Primary)';
      }

      $form['alerts']['alert'][$i]['alert_title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Alert title'),
        '#default_value' => !empty($site_alerts[$i]['alert_title']) ? $site_alerts[$i]['alert_title'] : '',
      ];

      if (!empty($domainOptions)) {
        $form['alerts']['alert'][$i]['alert_domain'] = [
          '#type' => 'select',
          '#title' => $this->t('Alert domain'),
          '#description' => $this->t('Select which domain this alert should show up on.'),
          '#options' => $domainOptions,
          '#empty_option' => $this->t('- Select domain -'),
          '#default_value' => !empty($site_alerts[$i]['alert_domain']) ? $site_alerts[$i]['alert_domain'] : '',
        ];
      }

      $form['alerts']['alert'][$i]['alert_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Alert type'),
        '#options' => [
          'c-site-alert__general' => $this->t('General'),
          'c-site-alert__warning' => $this->t('Warning'),
          'c-site-alert__advisory' => $this->t('Advisory'),
        ],
        '#empty_option' => $this->t('- Select an option -'),
        '#default_value' => !empty($site_alerts[$i]['alert_type']) ? $site_alerts[$i]['alert_type'] : '',
      ];

      if (empty($site_alerts[$i]['alert_dismiss'])) {
        $site_alerts[$i]['alert_dismiss'] = FALSE;
      }
      $form['alerts']['alert'][$i]['alert_dismiss'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Make this alert dismissable?'),
        '#default_value' => $site_alerts[$i]['alert_dismiss'],
      ];

      $form['alerts']['alert'][$i]['alert_dismiss_title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Dismissal title'),
        '#description' => $this->t('Dismissal button text/title.'),
        '#default_value' => !empty($site_alerts[$i]['alert_dismiss_title']) ? $site_alerts[$i]['alert_dismiss_title'] : 'Close',
        '#states' => [
          'visible' => [
            ':input[name="alerts[alert][' . $i . '][alert_dismiss]"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $message = !empty($site_alerts[$i]['alert_message']) ? $site_alerts[$i]['alert_message'] : [];
      $form['alerts']['alert'][$i]['alert_message'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Alert message'),
        '#default_value' => !empty($message['value']) ? $message['value'] : '',
      ];

      $alert_link_type = !empty($site_alerts[$i]['alert_link_type']) ? $site_alerts[$i]['alert_link_type'] : 'internal';
      $form['alerts']['alert'][$i]['alert_link_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Alert link type'),
        '#options' => [
          'internal' => $this->t('Internal'),
          'external' => $this->t('External'),
        ],
        '#empty_option' => $this->t('- Select an option -'),
        '#default_value' => $alert_link_type,
      ];

      // Set alert page for tweet preview.
      if (!empty($site_alerts[$i]['alert_link']) && empty($alert_page)) {
        $alert_page = $this->entityTypeManager->getStorage('node')
          ->load($site_alerts[$i]['alert_link']);
      }
      $form['alerts']['alert'][$i]['alert_link'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#selection_handler' => 'default',
        '#selection_settings' => [
          'target_bundles' => $site_alert_content_types,
        ],
        '#title' => $this->t('Alert internal link'),
        '#default_value' => $alert_page,
        '#states' => [
          'visible' => [
            ':input[name="alerts[alert][' . $i . '][alert_link_type]"]' => ['value' => 'internal'],
          ],
        ],
      ];

      $alert_external_link = !empty($site_alerts[$i]['alert_external_link']) ? $site_alerts[$i]['alert_external_link'] : '';
      $form['alerts']['alert'][$i]['alert_external_link'] = [
        '#type' => 'url',
        '#title' => $this->t('Alert external link'),
        '#default_value' => $alert_external_link,
        '#states' => [
          'visible' => [
            ':input[name="alerts[alert][' . $i . '][alert_link_type]"]' => ['value' => 'external'],
          ],
        ],
      ];

      $form['alerts']['alert'][$i]['alert_link_title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Alert link title'),
        '#default_value' => !empty($site_alerts[$i]['alert_link_title']) ? $site_alerts[$i]['alert_link_title'] : $this->t('See Details'),
      ];
    }

    $form['alerts']['actions'] = [
      '#type' => 'actions',
    ];

    $form['alerts']['actions']['add_alert'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add alert'),
      '#submit' => ['::addAlert'],
      '#ajax' => [
        'callback' => '::addAlertCallback',
        'wrapper' => 'site-alerts-wrapper',
      ],
    ];

    if ($num_alerts > 1) {
      $form['alerts']['actions']['remove_alert'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove alert'),
        '#submit' => ['::removeAlert'],
        '#ajax' => [
          'callback' => '::addAlertCallback',
          'wrapper' => 'site-alerts-wrapper',
        ],
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * Callback for both ajax-enabled buttons. Add alert callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   Return alerts.
   */
  public function addAlertCallback(array &$form, FormStateInterface $form_state) {
    return $form['alerts'];
  }

  /**
   * Submit handler for the "add alert" button.
   *
   * Increments the max counter and causes a rebuild.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addAlert(array &$form, FormStateInterface $form_state) {
    $num_alerts = $form_state->get('num_alerts');
    $num_alerts += 1;
    $form_state->set('num_alerts', $num_alerts);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "remove alert" button.
   *
   * Decrements the max counter and causes a form rebuild.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function removeAlert(array &$form, FormStateInterface $form_state) {
    $num_alerts = $form_state->get('num_alerts');
    if ($num_alerts > 1) {
      $num_alerts = $num_alerts - 1;
      $form_state->set('num_alerts', $num_alerts);
    }
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get form values.
    $form_values = $form_state->getValues();

    // Build alerts array.
    $site_alerts = [];
    $alerts = $form_values['alerts'];
    foreach ($alerts['alert'] as $alert_position => $alert) {
      $site_alerts[$alert_position] = $alert;
      $random = new Random();
      $site_alerts[$alert_position]['alert_dismiss_key'] = $random->string(8, TRUE);
    }

    // Set config.
    $this->setStateConfig(['site_alert.' . $this->language . '.alerts' => $site_alerts]);

    // Invalidate cache tags.
    Cache::invalidateTags(['sitewide_alerts']);

    // Status message.
    $this->messenger()
      ->addMessage($this->t('Site alert(s) saved successfully.'));
  }

}
