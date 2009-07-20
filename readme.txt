=== Hana Code Insert ===
Contributors: HanaDaddy
Donate link: http://www.neox.net/
Tags: AdSense,Paypal, PHP, Insert, adding code,embed code,code
Requires at least: 2.0
Tested up to: 2.8.1
Stable tag: 2.0

Easily insert any complicated HTML and JAVASCRIPT code or even custom PHP output in your Wordpress article. Useful for adding AdSense and Paypal donation code in the middle of the WP article.

== Description ==

Easily insert any complicated HTML and JAVASCRIPT code or even custom PHP output in your Wordpress article. Useful for adding AdSense and Paypal donation code in the middle of the WP article. You can manage multiple code entries.

After the installation you would setup the HTML or Javascript entries in the 'Hana Code Insert' Settings menu. Simply define a unique name and paste the complicated codes copied from AdSense or Paypal into the textarea. Then click  on the  'Create a new entry' button.

After the creation, you can find that the newly added entry is shown in the bottom. Copy the usage code example and insert it in your article. That's all.

Basically, you can place the specific tag element `[hana-code-insert]` in your wordpress article to show the codes. The 'name' attribute is mandatory where you use the name of the code entry that you want to show. 

For example,  after you setup a code entry in the admin Settings with the name 'AdSense', you can invoke the code by using below element in your article.

`
[hana-code-insert name='AdSense' /]
`
 

Thank you for using my plugin. -  [HanaDaddy](http://www.neox.net/)

*v2.0 (7/20/2009) : PHP evaluation settings can be set within the option page. Editor Helper Dialog button is added for easier insert. If you are upgrading , you should enable the PHP execution option from the option page if you were using PHP eval function previously.

*v1.5 (1/16/2009) : added htmlspecialchars function to prevent error occurred when using form and textarea tags. Also added 'Remove All' Button to delete all entries if something goes wrong.


== Installation ==

This section describes how to install the plugin and get it working.

1. Download and unzip the zip file. Upload `hana-code-insert` folder with all of its contents to the `/wp-content/plugins/` directory
2. Activate the plugin through the `Plugins` menu in WordPress Admin Interface.
3. Goto 'Settings' menu in the Admin Interface (Hana Code Insert) and create a code entry to use.
4. Use `[hana-code-insert name='...'/]` in your blog article.  Attribute `name` should be the name of the code entry you defined in the Settings menu. You can use Editor Helper Dialog for easier insert. Please see screen shot 3 for reference.


== Frequently Asked Questions ==



== Screenshots ==

1. Creating a new code entry in the Setting Screen
2. Showing List of code entries with the Usage for you to insert in your article.
3. New Editor Helper Dialog is added for easy browse and insert.
