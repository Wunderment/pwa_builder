#!/bin/bash

# Set the Android SDK root path for gradle.
export ANDROID_SDK_ROOT=~/android/sdk


# We need 1 command line parameters to work.
if [ ! $# -eq 1 ]; then
	echo "Incorrect usage: add-pwa-app.sh <app.json>"

    exit 1
fi

if [ ! -f $1 ]; then
	echo "ERROR: Application JSON file does not exists!"

	exit 1
fi

# Parse the JSON file for our variables.
TITLE=$(jq -Mr '.title' $1)
APPID=$(jq -Mr '.appid' $1)
PWAURL=$(jq -Mr '.url' $1)
PWADOMAIN=$(jq -Mr '.domain' $1)
PWASUBDOMAIN=$(jq -Mr '.subdomain' $1)
PCOLOUR=$(jq -Mr '.primaryColor' $1)
DCOLOUR=$(jq -Mr '.primaryColorDark' $1)
LCOLOUR=$(jq -Mr '.primaryColorLight' $1)

ROOTDIR=${PWD}

echo "Adding $TITLE ($PWAURL)..."

# Don't overwrite the PWA if it already exists.
if [ -d ./apps/$TITLE ]; then
	echo "Error: PWA already exists!"

	exit 1
fi

echo "Creating directory structure..."

# Make the PWA directory.
cd apps
mkdir $TITLE

cd $TITLE

APPDIR=${PWD}

# Copy the JSON file in to it's proper directory.
cp ../../$1 .

# Add the directories we're going to use.
mkdir icons
mkdir src
mkdir apk
mkdir screenshots

# Go get the icon from the website for the app.
cd $APPDIR/icons

# Make some working directories.
mkdir mipmap
mkdir drawable

# Make the mipmap directories: mdpi = 48x48, hdpi = 72x72, xhdpi = 96x96, xxhdpi = 144x144, xxxhdpi = 192x192
# Three files in each directory:
#	ic_launcher.png 			- A square with rounded corners and transparent border around it.
#	ic_launcher_foreground.png	- No background color, just the main content of the image.
#	ic_launcher_round.png		- A round icon with the background color and slight transparent border.
cd $APPDIR/icons/mipmap
mkdir mipmap-hdpi
mkdir mipmap-mdpi
mkdir mipmap-xhdpi
mkdir mipmap-xxhdpi
mkdir mipmap-xxxhdpi

# Make the drawable directories: mdpi = 24x24, hdpi = 36x36, xhdpi = 48x48, xxhdpi = 72x72, xxxhdpi = 192x192
# One file in each directory:
#	ic_appbar.png 			- No background color, just the main content of the image.
cd $APPDIR/icons/drawable
mkdir drawable-hdpi
mkdir drawable-mdpi
mkdir drawable-xhdpi
mkdir drawable-xxhdpi
mkdir drawable-xxxhdpi

# Go back up to the main icons directory.
cd $APPDIR/icons

echo "Downloading the favicons..."
# Get the favicons using favicon-downloader-cli from https://github.com/anubhavsrivastava/favicon-downloader-cli
favdownload $PWAURL

echo "Converting favicon to mipmap/drawable icons..."
# Convert the downloaded favicons to a single placeholder icon and then generate the various required icons for the app.
php $ROOTDIR/convert-to-icons.php $TITLE

echo "Copying the PWA template to the app directory..."
# Copy the PWA app template to our src directory.
cd $APPDIR/src
cp -R $ROOTDIR/template/* .

echo "Setting gradlew to executable..."
# Make gradlew executable.
chmod +x gradlew

## Update the various files in the template to customize it to the PWA.

echo "Updating the app config..."

# Update the URL's and domain's...
cd $APPDIR/src/app/src/main/java/at/xtools/pwawrapper

# Escape the backslashes in the PWA URL.
PWAURLESCAPED=$(echo $PWAURL | sed 's/\//\\\//g')

sed "s/https:\/\/www\.leasingrechnen\.at\//$PWAURLESCAPED/" Constants.java > Constants.new
sed "s/leasingrechnen\.at/$PWADOMAIN/" Constants.new > Constants.java
rm Constants.new

# Update the app name.
cd $APPDIR/src/app/src/main/res/values
sed "s/Leasing Rechner/$TITLE PWA/" strings.xml > strings.new
cp strings.new strings.xml
rm strings.new

# Update the colours.
sed "s/colorPrimary\">#....../colorPrimary\">$PCOLOUR/" colors.xml > colors.new
sed "s/colorPrimaryDark\">#....../colorPrimaryDark\">$DCOLOUR/" colors.new > colors.xml
sed "s/colorPrimaryLight\">#....../colorPrimaryLight\">$LCOLOUR/" colors.xml > colors.new
cp colors.new colors.xml
rm colors.new

# Update the icons.
cd ..
cp -r $APPDIR/icons/mipmap/* .
cp -r $APPDIR/icons/drawable/* .

# Update the app id and other build items.
cd $APPDIR/src/app
sed "s/at\.xtools\.pwawrapper/org.wunderment.pwa.$APPID/g" build.gradle > build.new
sed "s/www\.leasingrechnen\.at/$PWASUBDOMAIN/g" build.new > build.gradle
sed "s/leasingrechnen\.at/$PWADOMAIN/g" build.gradle > build.new
cp build.new build.gradle
rm build.new

echo "Start PWA build..."

# Time to actually build the PWA...
cd $APPDIR/src
./gradlew build

gradlew_return_code=$?
if (( gradlew_return_code != 0 )); then
  echo "Gradle failed with exit status $gradlew_return_code"
  exit
fi

# Copy the new APK.
echo "Renaming and storing the apk file..."
cp $APPDIR/src/app/build/outputs/apk/release/app-release-unsigned.apk $APPDIR/apk/$TITLE-PWA-unsigned.apk

# Sign the APK.
echo "Signing the apk..."
cd $APPDIR/apk
zipalign -v -p 4 $TITLE-PWA-unsigned.apk $TITLE-PWA-unsigned-aligned.apk
apksigner sign --key ~/.android-certs/releasekey.pk8 --cert ~/.android-certs/releasekey.x509.pem --out $TITLE-PWA.apk $TITLE-PWA-unsigned-aligned.apk

# Create a screenshot.
echo "Create screenshots..."
cd $APPDIR/screenshots
cutycapt --url=$PWAURL --out=$TITLE-noborder.png --min-width=600 --min-height=1067

# TBD: Add the PWA app template around the screenshot.
# php ../../add-screenshot-template.php $TITLE-noborder.png

echo "Finished!"
