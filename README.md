Overview
========

If you've ever come across the challenge to create a form with more than the default contact form fields in EE, chances are very good you know Solspaces's FreeForm already. For all of you who don't this would be the ideal time to have a look at the [FreeForm product website](http://www.solspace.com/software/detail/freeform/) as well as the [documentation page](http://www.solspace.com/docs/c/Freeform/) of this EE module.

In Short: with FreeForm you can build completely customized forms, managing the posted entries and user or administrator emails all from ExpressionEngine's control panel.

What FreeForm does not support out of the box is providing information about where the visitor who just filled out your form comes from or what his IP address is. Sure, you can define an IP field in FreeForm and a hidden input field in your form that will pass the IP through the form so that you can include it in your admin emails. With this extension, you have the possibility to either automatically append not only the IP address of your visitor, but also the location data belonging to that IP address.

You can also use the `{ip_location_data}` in those admin email templates you want to have location data included while turning of the automatically append feature.

Currently, this extension uses the free geolocate API from [http://www.hostip.info](http://www.hostip.info) to retrieve the location data for an IP address. However, we've added a *hook for developers* to be able to use another service if they wish.

Requirements
============

1.  You need to have the [Solspace Freeform](http://www.solspace.com/software/detail/freeform/) 2.7.1 installed, this extension won't work with any older version of FreeForm.
2.  As of this writing, Solspace does not provide the appropriate hooks for changing the contents of the email being sent to the admin in their latest FreeForm release. Solspace has however released a bug fix for this, you'll need to grab the mod.freeform.php file from [this post](http://www.solspace.com/forums/viewthread/486/#7466) and overwrite the one from the official release.
3.  CURL for PHP enabled

Installation
============

1.  Download DC FreeForm GeoIP Extension.
2.  Unpack the archive contents to your Desktop or to a location of your choice on your hard-drive.
3.  Copy the `extensions/ext.dc_freeform_geoip.php` file to your `/system/extensions` directory.
4.  Copy the `language/english/lang.dc_freeform_geoip.php` file to your `/system/language/english` directory (or duplicate and modify for any other language).

Activation & Settings
=====================

This extension does not have any special activation requirements (except for the requirements in order for it to run). Follow these steps to activate the extension in your EE installation:

1.  Log in to your EE control panel
2.  Go to `System Administration > Utilities > Extensions Manager` and enable extensions if not enabled already
3.  Enable DC FreeForm GeoIP extension

Currently, there are two settings for this extension:

1.  **Automatically append IP Location Data to notyfication emails**: When set to yes, the IP location data will automatically be appended to the admin notification emails. When this is set to no, you can control where in the emails the location data shoulda appear by placing `{ip_location_data}` somewhere in your email template(s).
2.  **Check for updates **: When set to yes, the extension will automatically check for updates. You need to have Leevi Graham's [LG Addon Updater](http://leevigraham.com/cms-customisation/expressionengine/lg-addon-updater/) extension installed and enabled for this.

Developers
==========

This extension provides a hook for other extensions to change the way how the location data for an IP is obtained and what is returned. The usage is very straight forward, all you need to do is implement the `dc_freeform_geocode_ip` hook in your extension and return whatever should either be appended to an email or replaced withing the `{ip_location_data}` tag in your admin email template.

We've provided a sample extension that uses this hook. Please not that if you have several extensions using the `dc_freeform_geocode_ip` hook, data from the last extension processed (according to the priority of the hook) will be used, but having more than one extension that delivers the location data probably does not make any sense anyway.

Feedback
========

This extension has been tested to work with Expressionengine 1.6.4 and 1.6.5 and should be compatible with EE 1.4.0 or greater and most modern browsers. If you find a bug or have another feature request for this, drop us a line and we will be more than glad to fix or consider it.