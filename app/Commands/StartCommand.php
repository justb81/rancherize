<?php namespace Rancherize\Commands;
use Rancherize\Commands\Traits\BuildsTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class StartCommand
 * @package Rancherize\Commands
 */
class StartCommand extends Command   {

	use BuildsTrait;

	protected function configure() {
		$this->setName('start')
			->setDescription('Start an environment on the local machine')
			->addArgument('environment', InputArgument::REQUIRED)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {

		$environment = $input->getArgument('environment');

		$this->getBuildService()->build($environment, $input);

		passthru('docker-compose -f ./.rancherize/docker-compose.yml up -d');

		return 0;
	}


}