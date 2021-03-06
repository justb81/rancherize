<?php namespace Rancherize\Plugin\Loader;

use Pimple\Container;
use Rancherize\Composer\PackageNameParser;
use Rancherize\Configuration\Configurable;
use Rancherize\Configuration\Services\ProjectConfiguration;
use Rancherize\Exceptions\PluginAlreadyRegisteredException;
use Rancherize\Plugin\Provider;
use Symfony\Component\Console\Application;

/**
 * Class ComposerPluginLoader
 */
class ComposerPluginLoader implements PluginLoader {

	/**
	 * @var PackageNameParser
	 */
	private $packageNameParser;

	/**
	 * @var ProjectConfiguration
	 */
	private $projectConfiguration;

	/**
	 * @param string $pluginName
	 * @param string $classpath
	 */
	public function register( string $pluginName, string $classpath) {
		/**
		 * @var Configurable $configurable
		 */
		$configurable = container('project-config');

		$plugins = $configurable->get('project.plugins');

		if( !is_array($plugins) )
			$plugins = [];

		if( in_array($classpath, $plugins) )
			throw new PluginAlreadyRegisteredException($classpath);

		$key = $this->removeVersionRestraint($pluginName);

		$plugins[$key] = $classpath;
		$configurable->set('project.plugins', $plugins);

		$this->projectConfiguration->save($configurable);
	}

	/**
	 * @param Application $application
	 * @param Container $container
	 */
	public function load( Application $application, Container $container) {
		$this->packageNameParser = $container[PackageNameParser::class];

		/**
		 * @var ProjectConfiguration $projectConfig
		 */
		$projectConfig = $container['project-config-service'];
		$this->projectConfiguration = $projectConfig;

		/**
		 * @var \Rancherize\Configuration\Configurable $configuration
		 */
		$configuration = $container['configuration'];
		$configuration = $projectConfig->load($configuration);

		$pluginClasspathes = $configuration->get('project.plugins');
		if( !is_array($pluginClasspathes) )
			$pluginClasspathes = [];

		$plugins = [];
		foreach($pluginClasspathes as $classpath) {
			$plugin = new $classpath;
			if( ! $plugin instanceof  Provider)
				continue;

			$plugin->setApplication($application);
			$plugin->setContainer($container);
			$plugin->register();

			$plugins[] = $plugin;
		}

		foreach($plugins as $plugin) {
			$plugin->boot();
		}
	}

	/**
	 * @param $pluginName
	 * @return string
	 */
	private function removeVersionRestraint( $pluginName ) {
		$packageName = $this->packageNameParser->parseName($pluginName);

		$withoutConstraints = $packageName->getProvider().'/'.$packageName->getPackageName();

		return $withoutConstraints;
	}
}