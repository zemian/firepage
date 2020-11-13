#!/bin/sh
SCRIPT_DIR=$(dirname $0)

if [[ $# -lt 1 ]]; then
  echo "ERROR: Missing site_name argument."
  exit 0
fi

if [[ $1 == 'release' ]]; then
  if [[ $# -lt 2 ]]; then
    echo "ERROR: Missing release_tag argument."
    exit 0
  fi
  RELEASE_TAG=$2
  
  TEMP_PATH=$SCRIPT_DIR/sites/release
  TEMP_SITE_PATH=$TEMP_PATH/firepage-$RELEASE_TAG
  if [[ -e $TEMP_SITE_PATH ]]; then
    rm -rf $TEMP_SITE_PATH
  fi
  git archive -o $TEMP_PATH/firepage-$RELEASE_TAG.zip $RELEASE_TAG
  mkdir -p $TEMP_SITE_PATH
  pushd $TEMP_SITE_PATH
  unzip ../firepage-$RELEASE_TAG.zip
  popd
fi

SITE_NAME=$1
SITE_PATH=$SCRIPT_DIR/sites/$SITE_NAME
export MARKNOTES_CONFIG=$SITE_PATH/.firepage.json
if [[ ! -e $MARKNOTES_CONFIG ]]; then
  echo "ERROR: The config file $MARKNOTES_CONFIG does not exists."
  exit 0
fi

echo "Loading config $MARKNOTES_CONFIG"
php -S localhost:3000
