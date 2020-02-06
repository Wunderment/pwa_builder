# pwa_builder
Script to build Android PWA's from just a few basic bits of information.

The script requires several other applications to be installed to support it:
* Cutecapt
* favdownload from https://github.com/anubhavsrivastava/favicon-downloader-cli
* PHP and the GD library
* Android SDK 
* Android build tools (zipalign and signapk)
* Signing certificates stored in ~/.android-certs

It also requires the [Android PWA wrapper](https://github.com/xtools-at/Android-PWA-Wrapper) from xtools-at to be installed in the template directory.

To run the script you need to create a json file with a few items in it (see example.json for details).

Once setup, you can run the script as such:

```add-pwa-app.sh example.json```

The script will create a new directory in the `apps` directory, copy over the template, download the favicons from the site, generate the Android icons, compile the applications and sign it.  The resulting output will be located in `apps\[Name]\apk` with the filename `[Name]-PWA.apk`.

The Android SDK is assumed to exist in ~/android/sdk, if it is not, edit `add-pwa-app.sh` to change the `ANDROID_SDK_ROOT` enviroment variable at the top of the script.
