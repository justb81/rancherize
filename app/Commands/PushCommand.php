<?php namespace Rancherize\Commands;
use Rancherize\Commands\Events\PushCommandInServiceUpgradeEvent;
use Rancherize\Commands\Events\PushCommandStartEvent;
use Rancherize\Commands\Traits\EventTrait;
use Rancherize\Commands\Traits\IoTrait;
use Rancherize\Commands\Traits\RancherTrait;
use Rancherize\Configuration\Configuration;
use Rancherize\Configuration\LoadsConfiguration;
use Rancherize\Configuration\Traits\EnvironmentConfigurationTrait;
use Rancherize\Configuration\Traits\LoadsConfigurationTrait;
use Rancherize\Docker\DockerAccessConfigService;
use Rancherize\Docker\DockerAccount;
use Rancherize\RancherAccess\Exceptions\NoActiveServiceException;
use Rancherize\RancherAccess\Exceptions\StackNotFoundException;
use Rancherize\RancherAccess\HealthStateMatcher;
use Rancherize\RancherAccess\InServiceCheckerTrait;
use Rancherize\RancherAccess\NameMatcher\CompleteNameMatcher;
use Rancherize\RancherAccess\NameMatcher\PrefixNameMatcher;
use Rancherize\RancherAccess\RancherAccessParsesConfiguration;
use Rancherize\RancherAccess\RancherAccessService;
use Rancherize\RancherAccess\SingleStateMatcher;
use Rancherize\Services\BlueprintService;
use Rancherize\Services\BuildService;
use Rancherize\Services\DockerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class StartCommand
 * @package Rancherize\Commands
 *
 * Push the given environment to rancher. This will trigger the blueprint to build the infrastructure and deploy or
 * upgrade it in the given stack in rancher
 */
class PushCommand extends Command implements LoadsConfiguration {

	use IoTrait;
	use RancherTrait;
	use LoadsConfigurationTrait;
	use EnvironmentConfigurationTrait;
	use InServiceCheckerTrait;
	use EventTrait;

	/**
	 * @var RancherAccessService
	 */
	private $rancherAccessService;

	/**
	 * @var DockerService
	 */
	private $dockerService;
	/**
	 * @var BuildService
	 */
	private $buildService;

	/**
	 * @var BlueprintService
	 */
	private $blueprintService;

	/**
	 * PushCommand constructor.
	 * @param RancherAccessService $rancherAccessService
	 * @param DockerService $dockerService
	 * @param BuildService $buildService
	 * @param BlueprintService $blueprintService
	 */
	public function __construct( RancherAccessService $rancherAccessService, DockerService $dockerService, BuildService $buildService, BlueprintService $blueprintService) {
		parent::__construct();
		$this->rancherAccessService = $rancherAccessService;
		$this->dockerService = $dockerService;
		$this->buildService = $buildService;
		$this->blueprintService = $blueprintService;
	}

	protected function configure() {
		$this->setName('push')
			->setDescription('Start or upgrade the given environment in Rancher')
			->addArgument('environment', InputArgument::REQUIRED)
			->addArgument('version', InputArgument::REQUIRED)
			->addOption('image-exists', 'i', InputOption::VALUE_NONE, 'Do not build and push the image to dockerhub')
		;
	}


	protected function execute(InputInterface $input, OutputInterface $output) {

		$this->setIo($input,$output);

		$environment = $this->getEnvironment( $input );
		$version = $input->getArgument('version');

		$configuration = $this->getConfiguration();
		$environmentConfig = $this->environmentConfig($configuration, $environment);

		if($this->rancherAccessService instanceof RancherAccessParsesConfiguration)
			$this->rancherAccessService->parse($configuration);
		$account = $this->rancherAccessService->getAccount( $environmentConfig->get('rancher.account') );

		$rancher = $this->getRancher();
		$rancher->setAccount($account)
			->setOutput($output)
			->setProcessHelper( $this->getHelper('process'));

		$stackName = $environmentConfig->get('rancher.stack');
		try {
			list($composerConfig, $rancherConfig) = $rancher->retrieveConfig($stackName);

			$this->buildService->createDockerCompose($composerConfig);
			$this->buildService->createRancherCompose($rancherConfig);
		} catch(StackNotFoundException $e) {
			$output->writeln("Stack not found, creating", OutputInterface::VERBOSITY_NORMAL);
			$rancher->createStack($stackName);

			$this->buildService->createDockerCompose('');
			$this->buildService->createRancherCompose('');
		}

		$repository = $environmentConfig->get('docker.repository');
		$versionPrefix = $environmentConfig->get('docker.version-prefix', '');

		$image = $repository.':'.$versionPrefix.$version;

		$dockerAccount = $this->login($configuration, $environmentConfig);

		$blueprint = $this->blueprintService->byConfiguration($configuration, $input->getArguments());
		$this->buildService
			->setVersion($version)
			->setDockerAccount($dockerAccount)
			->build($blueprint, $configuration, $environment, true);

		$dockerService = $this->dockerService;
		$dockerService->setOutput($output)
			->setProcessHelper($this->getHelper('process'));

		$this->buildImage($dockerService, $image, $dockerAccount);

		$name = $environmentConfig->get('service-name');

		$versionizedName = $name.'-'.$version;
		$isInServiceUpgrade = $this->inServiceChecker->isInService( $environmentConfig );
		if( $isInServiceUpgrade )
			$versionizedName = $name;

		try {
			$matcher = new PrefixNameMatcher($name);
			if( $isInServiceUpgrade )
				$matcher = new CompleteNameMatcher($name);

			$activeStack = $this->getRancher()->getActiveService($stackName, $matcher);

			if( $isInServiceUpgrade ) {
				$this->inServiceUpgrade( $stackName, $versionizedName, $environmentConfig );
				return 0;
			}

			$this->rollingUpgrade( $stackName, $activeStack, $versionizedName );
		} catch(NoActiveServiceException $e) {

			$this->createNewService( $stackName, $versionizedName, $environmentConfig);
		}

		return 0;
	}

	protected function login(Configuration $configuration, Configuration $config) {

		/**
		 * @var DockerAccessConfigService $dockerAccessService
		 */
		$dockerAccessService = container('docker-access-service');
		$dockerAccessService->parse($configuration);
		$dockerAccount = $dockerAccessService->getAccount( $config->get('docker.account') );

		return $dockerAccount;

	}

	/**
	 * @param InputInterface $input
	 * @param DockerService $dockerService
	 * @param Configuration $configuration
	 * @param Configuration $config
	 * @param $image
	 * @internal param $dockerAccount
	 */
	protected function buildImage(DockerService $dockerService, $image, DockerAccount $dockerAccount) {

		if ( $this->getInput()->getOption('image-exists') ) {
			$this->getOutput()->writeln("Option image-exists was set, skipping build.", OutputInterface::VERBOSITY_VERBOSE);

			return;
		}


		$server = $dockerAccount->getServer();
		if( !empty($server) ) {
			$serverHost = parse_url($server, PHP_URL_HOST);
			$image = $serverHost.'/'.$image;
		}

		$dockerService->build($image, './.rancherize/Dockerfile');
		$dockerService->login($dockerAccount->getUsername(), $dockerAccount->getPassword(), $dockerAccount->getServer());
		$dockerService->push($image);
	}

	/**
	 * @param $serviceNames
	 * @param $config
	 * @return PushCommandInServiceUpgradeEvent
	 */
	protected function makeInServiceEvent( $serviceNames, $config ): PushCommandInServiceUpgradeEvent {
		$inServiceUpgradeEvent = new PushCommandInServiceUpgradeEvent();
		$inServiceUpgradeEvent->setServiceNames( $serviceNames );
		$inServiceUpgradeEvent->setConfiguration( $config );
		$inServiceUpgradeEvent->setForceUpgrade( false );
		return $inServiceUpgradeEvent;
	}

	/**
	 * @param $serviceNames
	 * @param $config
	 * @return PushCommandStartEvent
	 */
	protected function makeStartEvent( $serviceNames, $config ): PushCommandStartEvent {
		$inServiceUpgradeEvent = new PushCommandStartEvent();
		$inServiceUpgradeEvent->setServiceNames( $serviceNames );
		$inServiceUpgradeEvent->setConfiguration( $config );
		return $inServiceUpgradeEvent;
	}

	/**
	 * @param $versionizedName
	 * @param $config
	 * @param $stackName
	 * @return array
	 */
	protected function inServiceUpgrade( $stackName, $versionizedName, Configuration $config ): array {
		$serviceNames = [$versionizedName];
		$startEvent = $this->makeInServiceEvent( $serviceNames, $config );
		$this->getEventDispatcher()->dispatch( PushCommandInServiceUpgradeEvent::NAME, $startEvent );
		$serviceNames = $startEvent->getServiceNames();
		$forcedUpgrade = $startEvent->isForceUpgrade();

		$this->getRancher()->start( './.rancherize', $stackName, $serviceNames, true, $forcedUpgrade );

		// Use default Matcher
		$stateMatcher = new SingleStateMatcher( 'upgraded' );
		if ( $config->get( 'rancher.upgrade-healthcheck', false ) )
			$stateMatcher = new HealthStateMatcher( 'healthy' );

		$this->getRancher()->wait( $stackName, $versionizedName, $stateMatcher );
		// TODO: set timeout and roll back the upgrade if the timeout is reached without health confirmation.

		$this->getRancher()->confirm( './.rancherize', $stackName, [$versionizedName] );
		return array($serviceNames, $startEvent);
	}

	/**
	 * @param $stackName
	 * @param $activeStack
	 * @param $versionizedName
	 */
	protected function rollingUpgrade( $stackName, $activeStack, $versionizedName ) {
		$this->getRancher()->upgrade( './.rancherize', $stackName, $activeStack, $versionizedName );
	}

	/**
	 * @param string $stackName
	 * @param string $versionizedName
	 * @param Configuration $config
	 */
	protected function createNewService( $stackName, $versionizedName, Configuration $config ) {
		$serviceNames = [$versionizedName];
		$startEvent = $this->makeStartEvent( $serviceNames, $config );
		$this->getEventDispatcher()->dispatch( PushCommandStartEvent::NAME, $startEvent );
		$serviceNames = $startEvent->getServiceNames();

		$this->getRancher()->start( './.rancherize', $stackName, $serviceNames );
	}


	/**
	 * Return the environment name to be loaded
	 *
	 * @param InputInterface $input
	 * @return string
	 */
	public function getEnvironment(InputInterface $input) {
		return $input->getArgument('environment');
	}
}