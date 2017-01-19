<?php

namespace VideoRecruit\Phalcon\Gedmo\DI;

use Doctrine\Common\Persistence\Mapping\Driver\AnnotationDriver;
use Gedmo\Mapping\MappedEventSubscriber;
use Phalcon\Config;
use Phalcon\Di;
use Phalcon\DiInterface;
use VideoRecruit\Phalcon\DI\Container;
use VideoRecruit\Phalcon\Doctrine\DI\DoctrineOrmExtension;
use VideoRecruit\Phalcon\Events\DI\EventsExtension;

/**
 * Class GedmoExtension
 *
 * @package VideoRecruit\Phalcon\Gedmo\DI
 */
class GedmoExtension
{
	const LISTENER_PREFIX = 'videorecruit.phalcon.gedmo.listener.';

	// extension constants
	const SOFTDELETEABLE = 'softDeleteable';
	const SORTABLE = 'sortable';
	const TIMESTAMPABLE = 'timestampable';

	/**
	 * @var Container
	 */
	private $di;

	/**
	 * @var array
	 */
	private $defaults = [
		self::SOFTDELETEABLE => FALSE,
		self::SORTABLE => FALSE,
		self::TIMESTAMPABLE => FALSE,
	];

	/**
	 * @var array
	 */
	private static $availableAnnotations = [
		'softDeleteable' => 'Gedmo\SoftDeleteable\SoftDeleteableListener',
		'sortable' => 'Gedmo\Sortable\SortableListener',
		'timestampable' => 'Gedmo\Timestampable\TimestampableListener',
	];

	/**
	 * GedmoExtension constructor.
	 *
	 * @param DiInterface $di
	 * @param array|Config $config
	 */
	public function __construct(DiInterface $di, $config)
	{
		$this->di = $di;

		if ($config instanceof Config) {
			$config = $config->toArray();
		} elseif (!is_array($config)) {
			throw new InvalidArgumentException(sprintf('Config has to be either an array or ' . 'a instance if %s.', Config::class));
		}

		// check whether both doctrine and events extensions were registered
		if (!$this->di->has(DoctrineOrmExtension::METADATA_DRIVER)) {
			throw new InvalidStateException('Doctrine ORM extension was not found. Did you register it before?');
		} elseif (!$this->di->has(EventsExtension::EVENT_MANAGER)) {
			throw new InvalidStateException('Events extension was not found. Did you register it before?');
		}

		$config = array_merge($config, $this->defaults);

		$this->loadExtensions($config);
	}

	/**
	 * @param DiInterface $di
	 * @param array|Config $config
	 * @return GedmoExtension
	 */
	public static function register(DiInterface $di, $config = NULL)
	{
		return new self($di, $config ?: []);
	}

	/**
	 * @param array $config
	 */
	private function loadExtensions(array $config)
	{
		// add listeners which are switched ON to the DI
		foreach (self::$availableAnnotations as $annotation => $listener) {
			if ($config[$annotation] !== TRUE) {
				continue;
			}

			$this->di->setShared(self::LISTENER_PREFIX . $annotation, function () use ($listener) {
				/** @var AnnotationDriver $driver */
				$driver = $this->get(DoctrineOrmExtension::METADATA_DRIVER);

				/** @var MappedEventSubscriber $listener */
				$listener = new $listener();
				$listener->setAnnotationReader($driver->getReader());

				return $listener;
			}, EventsExtension::TAG_SUBSCRIBER);
		}
	}
}