<?php

namespace Drupal\sitewide_alerts\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\node\Entity\Node;
use Drupal\Core\Language\LanguageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sitewide Alerts Block.
 *
 * @Block(
 *   id          = "sitewide_alerts",
 *   admin_label = @Translation("Sitewide Alerts"),
 *   category    = @Translation("Site Alert"),
 * )
 */
class SiteWideAlertsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The admin context.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * The current language id.
   *
   * @var string
   */
  protected $language;

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * SiteWideAlertsBlock constructor.
   *
   * @param array $configuration
   *   The configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Routing\AdminContext $admin_context
   *   The admin context.
   * @param \Drupal\Core\Language\LanguageManager $language_manager
   *   The language manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    AdminContext $admin_context,
    LanguageManager $language_manager,
    StateInterface $state
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->adminContext = $admin_context;
    $this->languageManager = $language_manager;
    $this->language = $this->languageManager->getCurrentLanguage()->getId();
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('router.admin_context'),
      $container->get('language_manager'),
      $container->get('state')
    );
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
      'site_alert.' . $this->language . '.alerts',
    ];
    return $this->state->getMultiple($state_keys);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $config = $this->getStateConfig();
    if ($config && $config['site_alert_active']) {
      if (!$this->adminContext->isAdminRoute()) {
        $site_alerts = !empty($config['site_alert.' . $this->language . '.alerts']) ? $config['site_alert.' . $this->language . '.alerts'] : [];
        $merge_site_alerts = $config['site_alert_merge'] ?? FALSE;
        $build = [
          '#theme' => ($merge_site_alerts) ? 'site_alert_merged' : 'site_alerts',
          '#alerts' => [],
        ];
        $alert_keys = [];
        foreach ($site_alerts as $site_alert_position => $site_alert) {
          $alert_link = NULL;
          $alert_link_id = !empty($site_alert['alert_link']) ? $site_alert['alert_link'] : 0;
          if (!empty($alert_link_id)) {
            // phpcs:disable
            $alert_link = $alert_link_node = Node::load($alert_link_id);
            // phpcs:enable
            if ($alert_link_node && $alert_link_node->hasTranslation($this->language)) {
              $alert_link = $alert_link_node->getTranslation($this->language);
            }
          }
          if ($merge_site_alerts) {
            if ($site_alert_position == 1) {
              $build['#primary_alert'] = [
                'type' => !empty($site_alert['alert_type']) ? $site_alert['alert_type'] : '',
                'position' => 'position-top',
                'title' => !empty($site_alert['alert_title']) ? $site_alert['alert_title'] : '',
                'message' => !empty($site_alert['alert_message']) ? $site_alert['alert_message'] : [],
                'dismiss' => !empty($site_alert['alert_dismiss']),
                'dismiss_title' => !empty($site_alert['alert_dismiss_title']) ? $site_alert['alert_dismiss_title'] : 'Close',
                'dismiss_key' => !empty($site_alert['alert_dismiss_key']) ? $site_alert['alert_dismiss_key'] : '',
                'link' => !empty($alert_link) ? $alert_link->toUrl()
                  ->toString() : '',
                'link_title' => !empty($site_alert['alert_link_title']) ? $site_alert['alert_link_title'] : '',
              ];
            }
            else {
              $build['#alerts'][$site_alert_position] = [
                'type' => !empty($site_alert['alert_type']) ? $site_alert['alert_type'] : '',
                'position' => 'position-top',
                'title' => !empty($site_alert['alert_title']) ? $site_alert['alert_title'] : '',
                'message' => !empty($site_alert['alert_message']) ? $site_alert['alert_message'] : [],
                'dismiss' => !empty($site_alert['alert_dismiss']),
                'dismiss_title' => !empty($site_alert['alert_dismiss_title']) ? $site_alert['alert_dismiss_title'] : 'Close',
                'dismiss_key' => !empty($site_alert['alert_dismiss_key']) ? $site_alert['alert_dismiss_key'] : '',
                'link' => !empty($alert_link) ? $alert_link->toUrl()
                  ->toString() : '',
                'link_title' => !empty($site_alert['alert_link_title']) ? $site_alert['alert_link_title'] : '',
              ];
            }
          }
          else {
            $build['#alerts'][$site_alert_position] = [
              '#theme' => 'site_alert',
              '#alert' => [
                'type' => !empty($site_alert['alert_type']) ? $site_alert['alert_type'] : '',
                'position' => 'position-top',
                'title' => !empty($site_alert['alert_title']) ? $site_alert['alert_title'] : '',
                'message' => !empty($site_alert['alert_message']) ? $site_alert['alert_message'] : [],
                'dismiss' => !empty($site_alert['alert_dismiss']),
                'dismiss_title' => !empty($site_alert['alert_dismiss_title']) ? $site_alert['alert_dismiss_title'] : 'Close',
                'dismiss_key' => !empty($site_alert['alert_dismiss_key']) ? $site_alert['alert_dismiss_key'] : '',
                'link' => !empty($alert_link) ? $alert_link->toUrl()
                  ->toString() : '',
                'link_title' => !empty($site_alert['alert_link_title']) ? $site_alert['alert_link_title'] : '',
              ],
            ];
          }
          $alert_keys[] = $site_alert['alert_dismiss_key'];
        }
        $build['#attached'] = [
          'library' => ['sitewide_alerts/alerts'],
          'drupalSettings' => [
            'sitewide_alerts' => [
              'dismissedKeys' => $alert_keys,
            ],
          ],
        ];
        $cacheableMetadata = new CacheableMetadata();
        $cacheableMetadata->addCacheableDependency($config);
        $cacheableMetadata->addCacheTags(['sitewide_alerts']);
        $cacheableMetadata->applyTo($build);
      }
    }
    return $build;
  }

}
