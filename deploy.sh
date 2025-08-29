#!/bin/bash
RELEASE_VERSION=$(cat advanced-cogs-profit-for-woocommerce.php | grep 'Version:' | awk -F' ' '{print $3}')

zip "./dist/advanced-cogs-profit-for-woocommerce-$RELEASE_VERSION.zip" readme.txt advanced-cogs-profit-for-woocommerce.php