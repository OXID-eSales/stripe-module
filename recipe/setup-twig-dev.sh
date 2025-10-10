#!/bin/bash
# Flags possible:
# -e for shop edition. Possible values: CE/EE
##
##  Use: cd oxideshop_root
##  git clone <git> extensions/paypal
##  bash:: ./extensions/paypal/recipe/setup-dev.sh -eEE
##

# set -x  # Enables debugging
set -e  # Stops script on any error


edition='EE'
branch='b-7.4.x'
while getopts e: flag; do
  case "${flag}" in
  e) edition=${OPTARG} ;;
  *) ;;
  esac
done

source "${BASH_SOURCE[0]%/*}/include.sh"

# Find the module root and set the working directory
MODULE_ROOT=$(find_module_root)
echo "Module root is: $MODULE_ROOT"
PROJECT_ROOT=$(find_project_root)
echo "Project root is: $PROJECT_ROOT"

# Check if docker-compose.yml.dist exists at the project root
if [ -f "$PROJECT_ROOT/docker-compose.yml.dist" ]; then
  echo "docker-compose.yml.dist found at $PROJECT_ROOT"
  # Optionally, copy it to docker-compose.yml if needed
  cp -n "$PROJECT_ROOT/docker-compose.yml.dist" "$PROJECT_ROOT/docker-compose.yml"
  echo "Created docker-compose.yml from docker-compose.yml.dist"
fi

cd "$PROJECT_ROOT" || exit 1

# Prepare services configuration
make setup
make addbasicservices
make file=services/adminer.yml addservice

echo "module root is $MODULE_ROOT"
cd "$PROJECT_ROOT" || exit 1

$MODULE_ROOT/recipe/parts/shared/prepare_shop_package.sh -e"${edition}" -b"${branch}" || exit 1
$MODULE_ROOT/recipe/parts/shared/require_twig_components.sh -e"${edition}" -b"${branch}"
$MODULE_ROOT/recipe/parts/shared/require_theme_dev.sh -t"apex" -b"v3.0.0" -e"${edition}"
$MODULE_ROOT/recipe/parts/shared/require_demodata_package.sh -e"${edition}" -b"${branch}"

mkdir -p "$PROJECT_ROOT"/source/extensions || exit 1
cp -r "$MODULE_ROOT" "$PROJECT_ROOT"/source/extensions/stripe || exit 1

mkdir -p ./source/var/configuration/environment/shops/1/modules
cp $MODULE_ROOT/recipe/environment/1.yaml ./source/var/configuration/environment/shops/1/modules/stripe.yaml


echo "composer require symfony/dotenv"
docker compose exec -T php composer require symfony/dotenv --no-interaction --no-update

echo "composer update"
docker compose exec -T php composer update --no-interaction

$PROJECT_ROOT/source/extensions/stripe/recipe/parts/shared/setup_database.sh


# Configure module in composer
docker compose exec -T \
  php composer config repositories.oxid-esales/stripe \
  --json '{"type":"path", "url":"./extensions/stripe", "options": {"symlink": true}}' || exit 1



make up
# Install all preconfigured dependencies
docker compose exec -T php composer update --no-interaction
docker compose exec -T php bin/oe-console oe:setup:demodata
docker compose exec -T php bin/oe-console oe:theme:activate apex
docker compose exec -T php bin/oe-console oe:module:install extensions/stripe
docker compose exec -T php bin/oe-console oe:module:activate stripe

$PROJECT_ROOT/source/extensions/paypal/recipe/parts/shared/create_admin.sh
# Register all related project packages git repositories

echo -e "\033[1;37m\033[1;42mInstallation is finished!\033[0m\n"
echo -e "\033[1;37m\033[1;42mYou can now access your shop at http://localhost.local/\033[0m\n"
echo -e "\033[1;37m\033[1;42mShop admin at http://localhost.local/admin\033[0m\n"
echo -e "\033[1;37m\033[1;42mYou can access the Adminer at http://localhost.local:8080/\033[0m\n"

#rm -rf "$MODULE_ROOT"

cp $PROJECT_ROOT/source/extensions/stripe/tests/.env.dist $PROJECT_ROOT/source/extensions/stripe/tests/.env

exit 0
