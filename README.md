# Rancherize

[![Latest Stable Version](https://poser.pugx.org/ipunkt/rancherize/v/stable.svg)](https://packagist.org/packages/ipunkt/rancherize) [![Latest Unstable Version](https://poser.pugx.org/ipunkt/rancherize/v/unstable.svg)](https://packagist.org/packages/ipunkt/rancherize) [![License](https://poser.pugx.org/ipunkt/rancherize/license.svg)](https://packagist.org/packages/ipunkt/rancherize) [![Total Downloads](https://poser.pugx.org/ipunkt/rancherize/downloads.svg)](https://packagist.org/packages/ipunkt/rancherize) [![Docker Pulls](https://img.shields.io/docker/pulls/ipunktbs/rancherize.svg)](https://hub.docker.com/r/ipunktbs/rancherize/) [![Docker Build Status](https://img.shields.io/docker/build/ipunktbs/rancherize.svg)](https://hub.docker.com/r/ipunktbs/rancherize/)

Rancherize is a php cli script based on symfony/console. It makes developing with docker and rancher easy for developers
without specialized knowledge in the subjects.
This is done by choosing a blueprint that fits your apps required environment and setting abstract requirements instead
of of adding and connecting services.

For a concrete example on how the configuration becomes easier through this see the example at the bottom of this page.

# Usage as docker container (preferred)
Rancherize comes bundled as Docker Container [`ipunktbs/rancherize`](https://hub.docker.com/r/ipunktbs/rancherize/).
## Requirements
Rancherize creates configuration to be used with external docker tools. Thus it is necessary to have the following tools
installed to use Rancherize:

- `docker` https://docs.docker.com/engine/installation/
## Install on linux
No need to separately install it. To use it, just make a shell alias:
```
alias rancherize='docker run -it -v $HOME/.rancherize:/home/rancherize/.rancherize -v /var/run/docker.sock:/var/run/docker.sock -v $(pwd):$(pwd) -w $(pwd) -e "USER_ID=$(id -u)" -e "GROUP_ID=$(id -g)"  ipunktbs/rancherize:2-stable'
```
or use the provided script `script/rancherize.sh`.

From now on use rancherize without other dependencies for your local environment than docker.

# Usage in build tools
With build tools like jenkins or gitlab-ci, you cannot rely on the presence of a .rancherize file in the home-dir. For this usecase you can set account settings with environment variables on the [docker container](https://hub.docker.com/r/ipunktbs/rancherize/) on runtime. best practise would be to include these variables via secrets.

- `DOCKER_SERVER` - registry server (e.g. registry.gitlab.com), ignore or leave empty for dockerhub
- `DOCKER_USER` - username for dockerhub / registry
- `DOCKER_PASSWORD` - password for dockerhub / registry
- `DOCKER_ECR` - true if using [AWS ECR](https://aws.amazon.com/ecr/)
- `RANCHER_URL` - rancher environment api-url
- `RANCHER_KEY` - rancher api-key
- `RANCHER_SECRET` - rancher api-secret

# Usage in project
## Requirements
Rancherize creates configuration to be used with external docker tools. Thus it is necessary to have the following tools
installed to use Rancherize:

- `docker` https://docs.docker.com/engine/installation/
- `docker-compose` https://docs.docker.com/compose/install/
- `rancher-compose` https://docs.rancher.com/rancher/v1.2/en/cattle/rancher-compose/#installation

## Installation
Rancherize is installed using composer

	composer require 'ipunkt/rancherize:^2.5.0'
	
# Configuration

## Accounts

Rancherize knows 2 types of accounts: 

- docker accounts. They are used to push images to docker hub
- rancher accounts. They are used to deploy your app to your rancher environment

Both are managed in the json file `~/.rancherize` which should be set to be only readable by your own user.  
For easy editing use the following command. It opens the file in your `$EDITOR` and creates a default file
if it does not exist yet.


	vendor/bin/rancherize rancher:access
	
## Environments

Rancherize configuration is split into `environments`. A typical app knows at least a `local` and a `production`
environment. Environments are configured by editing the file `rancherize.json` inside the app work directory. 

Note that all configuration values can also be set in the `defaults` section. Values in this section will be used if the
configuration value does not appear in the `environment`
See [Environments and Defaults](#environments-and-defaults) for a longer explanation on how to best use environments
 
The command `init` can be used to create an initial configuration for an environment.  
It will prompt the blueprint to create a sensible default production configuration. If the `--dev` Flag is used then
a configuration for a local development environment is created instead.


	vendor/bin/rancherize init [--dev] BLUEPRINT ENVIRONENTNAME1 ENVIRONEMNTNAME2... ENVIRONMENTNAMEX
	
	e.g.
	vendor/bin/rancherize init --dev webserver local
	vendor/bin/rancherize init webserver production staging


### Set Environment Variable
The command `environment:set` exists to conveniently set an environment value for all `environments`. It will go through
all `environments`, display the current value and ask for the new value. If none is given then the old value
will be used again.

	vendor/bin/rancherize environment:set VARIABLENAME

	e.g.
	vendor/bin/rancherize environment:set APP_KEY
	
## Development Environment

The command `start` exists to start an environment of your app on the local machine.

	vendor/bin/rancherize start ENVIRONMENTNAME
	
	e.g.
	vendor/bin/rancherize start local
	
Note that this command does not currently build a docker image from your work directory so the `environment` should be
set to mount your work directory directly. For the WebserverBlueprint this means setting

- `"use-app-container": false`
- `"mount-workdir":"true"`

Theses settings are included when initializing with the `--dev` flag

## Deploy
The command `push` exists to deploy the current state of your work directory into Rancher.  

	vendor/bin/rancherize push ENVIRONEMNT VERSION
	
	e.g.
	vendor/bin/rancherize push staging v1

- The current state of your work directory is build as docker image and tagged as
`$(docker.repository):$(docker.version-prefix)VERSION`  
- The built Image is pushed to docker hub using the credentials from the global configuration named `$(docker.account)`
- The current configuration of the stack in rancher is retrieved
  - If the stack does not exist yet it is created empty
- The apps configuration is added to the stack configuration
- The app is deployed into the stack
  - If no other version of the service is found it will be created
  - !NEW! If `rancher.in-service` is set to `true` then a rolling upgrade will be triggered to a non-versionized name
    and subsequently in-service upgrades of this service
  - If the same version of the service is found then an in-service upgrade is triggered
  - If a different version of the service is found then a rolling-upgrade ist triggered
  - In case of an in-service upgrade rancherize waits for the stack to reach `upgraded` and confirms the upgrade.  
    You can set `rancher.upgrade-healthcheck` to `true` to wait for it to report `healthy` instead. Not that this only
    works if a service has a health-check is defined(not yet supported through rancherize)
  
# Blueprints

## Known Blueprints

Currently only the [WebserverBlueprint](app/Blueprint/Webserver/README.md) is available. 

## Developing Blueprints

See the [Blueprint readme](app/Blueprint/README.md) for more information on how to develop your own blueprints

# Example
This example tries to illustrate how it becomes easier to add services to work with your app when using rancherize
blueprints when compared to directly writing docker-compose.yml files.

The following is an extract of a `docker-compose.yml` file. It starts a database and a phpmyadmin that connects to the
database. The part connecting the app with these services is omited.
```yaml
Database:
  image: 'mysql/mysql'
  tty: true
  environment:
    MYSQL_ROOT_PASSWORD: root
  stdin_open: true
  restart: unless-stopped
PMA:
  image: 'phpmyadmin/phpmyadmin:4.6.2-3'
  tty: true
  ports:
    - '8082:80'
  stdin_open: true
  links:
    - 'Database:db'
  labels:
    db: null
  restart: unless-stopped
```

becomes 

The folllowing is an extract of a `rancherize.json` file, adding the same services as above and connecting them to the app service.
```json
{
	"environments":{
		"example-environment": {
			"add-database": true
		}
	}
}
```

The required knowledge is reduced from
- I need a service named Database
- I need a service named PMA
- I need to connect my app service to the service Database
- I need to connect my app service to the service Redis
- The Service Database uses the image `mysql/mysql`
- Configuration required by the image `mysql/mysql`
- The Service PMA uses the image `phpmyadmin/phpmyadmin:4.6.2-3`
- Configuration required by the image `phpmyadmin/phpmyadmin:4.6.2-3`

To
- I wish to use add a database
- The database also starts a pma unless I disable it

## Environments and Defaults
The configuration for an app can be roughly split in two parts:
- app specific configuration
  - database access
  - exposed ports
  - exposed urls
  - links to consumed services
  - docker repository
  - rancher repository
- environment specific configuration
  - table name prefix
  - links to attached micro services
  
Rancherize tries to support this by splitting the rancherize.json in `environments` and a `default` section.  
A complete environment is generated by taking the `default` section, then adding the chosen environment, overriding values
if necessary.
Strictly speaking the `default` section is not necessary and the following two examples will act exactly the same but keeping
environment independant configuration there makes the file more readable and changing it easier as it must only be done
in a single place.

- Example with default section an two environments
```json
{
	"default":{
		"external_links":{
			"database":"DB/MySql"
		},
		"environment":{
			"DB_HOST":"database",
			"DB_USER":"example_user",
			"DB_PASSWORD":"example_password",
			"DB_DATABASE":"example_db"
		}
	},
	"environments":{
		"production":{
			"environment":{
				"APP_ENV":"production"
			}
		},
		"staging":{
			"environment":{
				"APP_ENV":"staging"
			}
		}
	}
}
```

- Example with two environments and no default
```json
{
	"environments":{
		"production":{
			"external_links":{
				"database":"DB/MySql"
			},
			"environment":{
				"APP_ENV":"production",
				"DB_HOST":"database",
				"DB_USER":"example_user",
				"DB_PASSWORD":"example_password",
				"DB_DATABASE":"example_db"
			}
		},
		"staging":{
			"external_links":{
				"database":"DB/MySql"
			},
			"environment":{
				"APP_ENV":"staging",
				"DB_HOST":"database",
				"DB_USER":"example_user",
				"DB_PASSWORD":"example_password",
				"DB_DATABASE":"example_db"
			}
		}
	}
}
```
