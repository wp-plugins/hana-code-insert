<?php
/*
Plugin Name: Hana Code Insert
Plugin URI: http://wpmarketing.org/plugins/hana-code-insert/
Description: Easily insert any complicated HTML and JAVASCRIPT code or even custom PHP output in your Wordpress article. Useful for adding AdSense and Paypal donation code in the middle of the WP article.
Version: 2.3
Author: HanaDaddy
Author URI: http://neox.net
*/

 
class hana_code_insert
{
	//---------------------------------------------
	// variable that can be modified
	//---------------------------------------------
	//var $eval_php=false;	//true : Activate the php evaluation, false: disable the php evaluation
	var $edit_col=80; 	//Textarea columns
	var $edit_row=5;  	//Textarea rows
	var $edit_wrap='on'; //off: wrap='off' , on : wrap='soft'
	//---------------------------------------------

	var $user_data;
	
	var $edit_wrap_str;
	var $tag_name="hana-code-insert";
	var $plugin_folder="hana-code-insert";
	var $plugin_url;
	
	var $admin_setting_menu='&#8226;Hana Code Insert';
	var $admin_setting_title='Hana Code Insert Default Configuration';
	var $update_result='';
	var $error_result='';
	var $failed_entry;
	
	var $entity_target = array("&#8217;","&#8220;","&#8221;","&#038;","\'","&#8242;", "&#8216;");
	var $entity_replace= array("'",'"','"',"&","'","'","'");
	var $settings;
	
	var $coderemove= array('\\','^');
  	var $codesearch_arr =array('[','$','.','|','?','*','+','(',')','{','}');
	var	$codereplace_arr=array('\[','\$','\.','\|','\?','\*','\+','\(','\)','\{','\}');
	
	
					
	function hana_code_insert() {
		
		$this->plugin_url=get_bloginfo("wpurl") . "/wp-content/plugins/$this->plugin_folder";
		
		if ($this->edit_wrap=='off')
			$this->edit_wrap_str="wrap='off'";
		else
		if ($this->edit_wrap=='on')
			$this->edit_wrap_str="wrap='on'";
		
		$this->settings = get_option('hanacode_settings');
		if (! $this->settings){
			$this->settings=array('edit_col'=>80,'edit_row'=>5,'edit_wrap'=>'on','shortcode_start'=>'','shortcode_end'=>'');
		}
		
		$this->bind_hooks();
	}
	
	function load_user_data(){
		//load user data only if user_data is not loaded
		if (! $this->user_data ){
			$this->user_data = get_option('hanacode_options');		
			if (! $this->user_data )
				$this->user_data=array();
		}
	}
	//used for html output
	function get_shortcode_start_html($enc=1){
		$ret=str_replace($this->codereplace_arr,$this->codesearch_arr,$this->settings['shortcode_start']);
		if ($enc) { $ret=htmlentities($ret); }
		return $ret;
	}

	function get_shortcode_end_html($enc=1){		
		$ret= str_replace($this->codereplace_arr,$this->codesearch_arr,$this->settings['shortcode_end']);		
		if ($enc) { $ret=htmlentities($ret); }
		return $ret;
	}
			
	function bind_hooks() {
		// third arg should be large value to execute in the later in the chain
		add_filter('the_content', array(&$this,'hana_code_return') , 1000);
		add_action('admin_menu' , array(&$this,'hana_code_admin_menu') );
		// init process for button control
		add_action('init', array(&$this,'hana_code_addbuttons'));
		add_action('admin_print_scripts',array(&$this,'admin_javascript'));
	}
	
	function hana_code_return($content) {
		if ($this->settings['shortcode_start'] != ''){
			$start=$this->settings['shortcode_start'];
			$end=$this->settings['shortcode_end'];
		    $reg='^'.$start.'(.*?)'.$end.'^ims';
		    //echo "REG:".htmlentities($reg) . "<br />";
			$content= preg_replace_callback($reg, array(&$this,'hana_code_callback_custom'), $content);			
		}
		//need to check on the original short code style always.
		return preg_replace_callback('^\['.$this->tag_name.'(.*?)/\]^ims', array(&$this,'hana_code_callback'), $content);
		
	}
 
	function hana_code_admin_menu() {
		if ( function_exists('add_options_page') ) {
			add_options_page($this->admin_setting_title,$this->admin_setting_menu, 1, __FILE__,array(&$this,'hana_code_options_page'));

		}
	}

	function hana_code_find($key){
		 
		$total=count($this->user_data);

		$found=null;
		for ($i=0;$i<$total ; $i++){
			$cur = $this->user_data[$i];	
			if ($cur['name'] == $key){
					$found=$cur;
					break;	
			}
		}

		
		$output='';
		if ($found){
			if ($found['php'] == '1' && $this->settings['enable_php'] =='yes' ){
				// need to insert that \n because of the possible user comment //
				if (strstr($found['content'],'<?php') === FALSE){
					//no <?php is used -> only php code need to be used.
					$phpcode="ob_start(); ".$found['content'] . "\n \$hana_final_output = ob_get_contents(); ob_end_clean(); "; 
				}else{
					$phpcode="ob_start(); ?>".$found['content'] . "\n<?php \$hana_final_output = ob_get_contents(); ob_end_clean(); "; 
				}
				eval($phpcode); //can be dangerous
				$output=$hana_final_output;
			}else{
				$output=$found['content'];			
			}
		}
		return $output;
	
	}
	
	function hana_code_callback($arg) {
		$this->load_user_data();
		//print($arg[1]);
		
		// hana_code_calback is called late to prevent HTML entities encoded(probably called in the early stage of chain).
		// but therefore, the input data is already encoded. So we need to convert back to original data.
		$fixed=str_replace($this->entity_target,$this->entity_replace,clean_pre($arg[1]));
		$attr_array=$this->parse_attributes($fixed);
	
		
		//$key_list = array_keys($attr_array['name']);
		
		if (! array_key_exists('name',$attr_array))	{
			return '<div style="color:#f00;font-weight:bold;">['.$this->tag_name.'] "name" attribute is mandatory. It must match one of your code items that you defined in the Hana Code Insert Settings</div>';	 	
		}
		
		$output = $this->hana_code_find($attr_array['name']);
		
		if ($output== '' && $cur['php'] != '1' ){
			$output= "<div style='color:#f00;font-weight:bold;'>[ ".$this->tag_name." ] '". $attr_array['name']."' is not found </div>";
			
		}
		//echo "[output] $output";
		
		return $output;				
			
	    
    }
	
	function hana_code_callback_custom($arg) {
		$this->load_user_data();
		$key = trim($arg[1]);
		if ($key == '')	{
			return '<div style="color:#f00;font-weight:bold;">['.$this->tag_name.'] entry name is missing. It must match one of your code items that you defined in the Hana Code Insert Settings</div>';	 	
		}
		$output = $this->hana_code_find($key);
		
		if ($output== '' && $cur['php'] != '1' ){
			//10/4/2009 : let's just ignore the error message for now.
			//$output= "<div style='color:#f00;font-weight:bold;'>[ ".$this->tag_name." ] '$key' is not found. You may want to check your custom shorcode format settings (
			//	Short code Start:<code>". $this->get_shortcode_start_html(). "</code>, End:<code>". $this->get_shortcode_end_html()."</code> )</div>";
			
		}
		return $output;				
	
	}
	    
	function hana_code_options_page() {
		$this->load_user_data();

		
	
		if ($_POST['form_name'] == $this->tag_name) {
		
			// admin option page update
			if ( isset($_POST['new_name']) ) {
				$this->hana_code_options_new();
			}
		
			if ( substr($_POST['submit'],0,10) == 'Remove All'){
				//print "haha-deleting";
				$this->hana_code_options_delete_all();	
			}else
			if ( substr($_POST['submit'],0,6) == 'Delete'){
				//print "haha-deleting";
				$this->hana_code_options_delete();	
			}else
			if ( substr($_POST['submit'],0,6) == 'Update'){
				$this->hana_code_options_update();
			}else
			if ( $_POST['submit'] == 'Save Settings'){
				$this->hana_code_save_settings();
				
			}
		}		
		
		//global  $_POST;
		if ( $this->update_result != '' ) 
			print '<div id="message" class="updated fade"><p>' . $this->update_result . '</p></div>';
		if ( $this->error_result != '')
			print '<div id="message" class="error fade"><p>' . $this->error_result . '</p></div>';
					
	?>
<style>
div.division {
	margin-top:10px;
	padding:5px 10px 5px 10px;
	border: 2px solid black;
	-moz-border-radius: 10px;
	border-radius: 10px;
}
h3 {
	font-size:1.5em;
}

</style>
<script LANGUAGE="JavaScript">
<!--
// Nannette Thacker http://www.shiningstar.net
function confirmSubmit()
{
	var agree=confirm("Are you sure you wish to remove all entries?");
	if (agree)
		return true ;
	else
		return false ;
}
// -->
</script>
<div class="wrap">
 	<div id="icon-options-general" class="icon32"><br /></div>
	<h2>Configuration for Hana Code Insert</h2>
	<p>
	Easily insert any complicated HTML and JAVASCRIPT code or even custom PHP output in your Wordpress article.
	Useful for adding AdSense and Paypal donation code in the middle of the WP article. You can manage multiple code entries.
    </p>
    <p>
    After the creation, you can find that the newly added entry is shown in the bottom. Copy the usage code example and insert it in your article. That's all.
    </p>
   
 	<form action="" method="post">
	<input type='hidden' value='<?php print $this->tag_name; ?>' name='form_name' />
	If something goes wrong and you want to start fresh, click this button. It will erase all the entries. <span class="submit"><input name="submit" value="Remove All" type="submit" onClick="return confirmSubmit()"/></span>

<div class='division'>
	<h3>Settings</h3>
	<ul style='list-style-type:circle;margin-left:30px;'>
	<li><strong>Editor textarea size:</strong> Columns <input type='text' name='edit_col' size='2' value='<?php echo $this->settings['edit_col']?>'> Rows <input type='text' name='edit_row' value='<?php echo $this->settings['edit_row'];?>' size='2'></li>
    <li><strong>Custom Short Code format:</strong> 
    Start indicators:<input type='text' name='shortcode_start' size='5' value='<?php echo $this->get_shortcode_start_html(); ?>'/> , 
    End indicators:<input type='text' name='shortcode_end' size='5' value='<?php echo $this->get_shortcode_end_html(); ?>' /><br />
    
    If defined, custom short code format can be used instead of <code>[hana-code-insert name='Entry Name' /]</code>. For example, if you define <code>{{</code> for start, <code>}}</code> for end, you can use <code>{{Entry Name}}</code>. But beware, your new shortcode format may conflict with other plugins and cause one or more plugins to fail.
    <strong>Note:</strong><code>^</code> and <code>\</code> characters are not allowed. And start and end indicators should not be used part of entry name.</code>
	</li>    
	<li><input type='checkbox' name='enable_php' <?php  if ($this->settings['enable_php'] == 'yes') echo 'checked'; ?> value='yes' /> <strong>Enable PHP Execution</strong><br />
	If you enable this option, the code entry can be evaluated as php codes. The output string will be embeded in the middle of your WP article. Don't need <code>&lt;?php</code> and <code>?&gt;</code></li>
	</ul>
	
	<p class="submit"><input type='submit' name='submit' value='Save Settings'></p>
	
	</form>
</div>

<div class='division'>
	<h3>New Entry</h3>
	<form action="" method="post">
	<input type='hidden' value='<?php print $this->tag_name; ?>' name='form_name'>
	<fieldset  class="options">
	<table id="optiontable" class="editform">
		<tr>
			<td valign="top" width='150'>New Entry Name:<br /><input type='text' name="new_name" value='<?php print $this->failed_entry['name']; ?>' size='15'>
			</td>
			<td>HTML code or Javascript or anything else you want to show in your article.<br />
			<textarea rows="<?php echo ( $this->settings['edit_row'] + 2); ?>" cols="<?php echo $this->settings['edit_col'] ?>" name="new_content" <?php echo $this->edit_wrap_str; ?>><?php print $this->failed_entry['content']; ?></textarea><br>
				<?php if ($this->settings['enable_php'] == 'yes') : ?>
					<input type='checkbox' name='new_php' value='1' <?php if ($this->failed_entry['php'] == '1') { print "checked"; } ?> > Evaluate as php code.
				<?php endif ?>
			</td>
		</tr>
	</table>
	<p class="submit"><input name="submit" value="Create New Entry &raquo;" type="submit"></p>
	</fieldset>
	</form>
</div>

<div class='division'>
	 
	<h3>Edit Existing Entries</h3>
	<form action="" method="post">
	<input type='hidden' value='<?php print $this->tag_name; ?>' name='form_name'>
	<fieldset  class="options">
	<table id="optiontable2" class="editform">	

	<?php 
	 $total = count($this->user_data); 	
	 //print "total : $total\n";
	 //print_r ($this->user_data);
	 for ($i=0; $i< $total ; $i++){
	 	$cur = $this->user_data[$i];

	 	$php_checked="";
	 	if ($cur['php']=='1') { $php_checked="checked"; } 

	 	print "<tr><input type='hidden' name='update_name_$i' value='" .$cur['name']."'>
			<td valign='top' width='150' ><input type='checkbox' name='delete[]' value='$i'> " .$cur['name']."</td>
			<td><textarea rows='". $this->settings['edit_row'] ."' cols='". $this->settings['edit_col'] ."' name='update_content_$i' ". $this->edit_wrap_str .">".htmlspecialchars($cur['content'])."</textarea><br />";
		if ($this->settings['enable_php'] == 'yes') 
			print "	
				<input type='checkbox' name='update_php_$i' value='1' $php_checked > Evaluate as php code.<br />";
		else
			print "
				<input type='hidden' name='update_php_$i' value='".$cur['php']."'>";
		print"
			usage: <code>[".$this->tag_name." name='".$cur['name']."' /]</code> ";

		if ($this->settings['shortcode_start'] != '' ) 
			print "or <code> ".$this->get_shortcode_start_html().$cur['name'].$this->get_shortcode_end_html()."</code>";
		
		print "
			</td>
		</tr>\n";
	 } 
	 ?>	 
		
	</table>
	<p class="submit"><input name="submit" value="Update Entries &raquo;" type="submit"> 
	<input type='submit' name='submit' value='Delete &raquo;'></p>
	</fieldset>
	</form>
 
</div>

    <p>Thank you for using my plugin. - <a href='http://wpmarketing.org/'>HanaDaddy</a></p>

    <p>
    <a href="http://wpmarketing.org/donate.php?id=hanacode" target="_new"><img src="https://www.paypal.com/en_US/i/btn/btn_donate_SM.gif" border="0" /></a>
    </p>


<script type="text/javascript" src="http://wpmarketing.org/plugin_news.php?id=<?php echo urlencode($this->tag_name); ?>"></script>

 

<?php

	}

	function check_item_exists($name){
		
		foreach($this->user_data as $item){
			if ($item['name']== "$name")
				return true;
			
		}
		return false;
	}

	function filter_input($type,$value){
		if ($type == 'name')
		{
			//allow only Alphanumeric and space, underscore, - 
			$temp = preg_replace("/[^a-zA-Z0-9 \-_]/", "", trim($value));
			return 	str_replace(array('\\', "'" , '"'), array('','',''), $temp );
		
		}else
		if ($type == 'content')
			return  str_replace(array('\\"', "\\'"), array('"',"'"), trim($value) );
		else
			return '';	
	}
	function hana_code_options_new(){
		$this->load_user_data();
				
		
		if ( isset($_POST['new_name']) ) {			
			$new['name'] = $this->filter_input('name',$_POST['new_name']);
		}
		if ( isset($_POST['new_php']) ) {
			$new['php'] = $_POST['new_php'];
		}
		if ( isset($_POST['new_content']) ) {
			$new['content'] = $this->filter_input('content',$_POST['new_content']);
		}	
	   
		if ($new['name'] == '' || $new['content'] == '' ) {
			$this->failed_entry = $new;
			$this->error_result="Name or Html Code is empty. ";			
		}else{
			
			if (! $this->check_item_exists($new['name'])){
				array_push($this->user_data,$new);

				update_option('hanacode_options',$this->user_data);
				$this->update_result="New entry is created.";
			}else{
				$this->failed_entry = $new;
				$this->error_result ="'".$new['name'] . "' already exists.";	
			}
		}
	}
	
	function hana_code_options_delete_all (){
		
		//$this->load_user_data();
		$this->user_data=null;
		update_option('hanacode_options',$this->user_data);
		$this->update_result="All items are deleted";
	}
	function hana_code_options_delete (){
		$this->load_user_data();
		$narr=array();
		$total=count($this->user_data);
		
		$delete = $_POST['delete'];
		if (!$delete) { $delete = array(); }
		
		$total_deleted=0;

		foreach($delete as $item){
			$key = "update_name_$item";
			$delete_name=$_POST[$key];
			
			for ($i=0; $i<$total; $i++){			
				if ($this->user_data[$i]['name'] == $delete_name){
					$this->user_data[$i]=null;	
					$total_deleted ++;
				}
			}
		}
		
		$j=0;
		for ($i=0;$i<$total ; $i++){
			if ($this->user_data[$i] != null)
				$narr[$j++]	= $this->user_data[$i];
		}

		$this->user_data =  $narr;
		//$this->user_data =  array();
		
		update_option('hanacode_options',$this->user_data);
		
		if ($total_deleted <= 1){
			$wording="item is deleted.";
		}else{
			$wording="items are deleted.";
		}
		$this->update_result="$total_deleted $wording";
		
	}
	function hana_code_options_update(){
		$this->load_user_data();
		
		$total=count($this->user_data);
		$total_updated=0;
		for($i=0;$i<$total ; $i++){
			$key = "update_name_$i";
			$update_name=$this->filter_input('name',$_POST[$key]);
			if ($update_name == '') break;  // end of update item.
			
			for ($j=0; $j<$total; $j++){			
				if ($this->user_data[$j]['name'] == $update_name){
					$this->user_data[$j]['content']=$this->filter_input('content',$_POST["update_content_$i"]);	
					if ($this->settings['enable_php'] == 'yes')
						$this->user_data[$j]['php']=$_POST["update_php_$i"];
					$total_updated ++;
				}
			}
		}
		

		update_option('hanacode_options',$this->user_data);
		
		if ($total_updated <= 1){
			$wording="item is updated.";
		}else{
			$wording="items are updated.";
		}
		$this->update_result="$total_updated $wording";
				
	}
	
	function hana_code_save_settings(){
		if ($_POST['enable_php'] == 'yes') 
			$this->settings['enable_php']='yes';
		else
			$this->settings['enable_php']='no';

		$col=intval($_POST['edit_col']);
		if ($col > 200) $col=200;
		$this->settings['edit_col'] = $col; 

		$row=intval($_POST['edit_row']);
		if ($row > 50) $row=50;
		$this->settings['edit_row'] =$row; 
		
		$shortcode_start= trim($_POST['shortcode_start'] );
		$shortcode_end= trim($_POST['shortcode_end'] );

		// regex special characters \^[$.|?*+(){}

		$shortcode_start=str_replace($this->coderemove,'',$shortcode_start);
		$shortcode_end =str_replace($this->coderemove,'',$shortcode_end);
		//echo "start :$shortcode_start<br />";
		//echo "end :$shortcode_end";
		$shortcode_start=str_replace($this->codesearch_arr,$this->codereplace_arr,$shortcode_start);
		$shortcode_end=str_replace($this->codesearch_arr,$this->codereplace_arr,$shortcode_end);
		
		//echo "start :$shortcode_start<br />";
		//echo "end :$shortcode_end";

		if ($shortcode_start != '' && $shortcode_end != ''){
			$this->settings['shortcode_start']=$shortcode_start;
			$this->settings['shortcode_end']=$shortcode_end;			
		}else{
			$this->settings['shortcode_start']='';
			$this->settings['shortcode_end']='';
		}
		
		update_option('hanacode_settings',$this->settings);
		$this->update_result="Saved Settings.";
	}
    	

    //Support function-----------------------------------------------------

    
    function parse_attributes($attrib_string){

		//first str_replace \n => ' '
		// new line are already stored as <br \> , so need to convert to space
		$search_arr = array("\n","<br />","\t");
	    $replace_arr = array(" "," "," ");	
		$attrib_string = str_replace($search_arr,$replace_arr,$attrib_string);
	
		
	    $regex='@([^\s=]+)\s*=\s*(\'[^<\']*\'|"[^<"]*"|\S*)@';
		
	    preg_match_all($regex, $attrib_string, $matches);
	
		$attr=array();
	
		//print_r($matches);
		for ($i=0; $i< count($matches[0]); $i++) {
	  		if ( ! empty($matches[0][$i]) && ! empty($matches[1][$i]))  {
				
	  			
	  			if (preg_match("/'(.*)'/",$matches[2][$i],$vmatch)) {
					$value=$vmatch[1];	
				}else 
				if (preg_match('/"(.*)"/',$matches[2][$i],$vmatch)) {
					$value=$vmatch[1];	
				}else{
					$value=$matches[2][$i];
				}
				$key=strtolower($matches[1][$i]);
				$attr[$key]= $value ;
				
			}
		}
	   
		return $attr;
		
	}
	
	function construct_attributes($arr){
	
		$output="";
		
		reset($arr);
		while (list($key, $value) = each ($arr)) {
			$envelop_char='"';
			
			if (strstr($value,'"') !== false) {
				
				$envelop_char='\'';			
			}
			$output .= " $key=".$envelop_char.$value.$envelop_char;
		}
		
		return $output;
	}
    
	//Editor plugin-----------------
	

//test-----------------
	
	function hana_code_addbuttons() {
	   	// Don't bother doing this stuff if the current user lacks permissions
	   	if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') )
	    	return;
	    //rich_editing
	    add_filter("mce_external_plugins", array(&$this,'add_tinymce_plugin'));
	    add_filter('mce_buttons', array(&$this,'register_button'));
	     
	    //for html editing 
	    add_action('edit_form_advanced', array(&$this,'print_javascript'));
		add_action('edit_page_form',array(&$this,'print_javascript'));
	    //add_action('admin_footer','print_javascript');
	}
	 
	function register_button($buttons) {
	   	//array_push($buttons, "separator", "hcinsert");
	   	array_push($buttons,  "hcinsert");
	   	return $buttons;
	}
	 
	// Load the TinyMCE plugin : editor_plugin.js (wp2.5)
	function add_tinymce_plugin($plugin_array) {
	   	$plugin_array['hanacodeinsert'] = $this->plugin_url . '/tinymce3/editor_plugin.js';
	   	return $plugin_array;
	}
	
	function admin_javascript(){
		//show only when editing a post or page.
		if (strpos($_SERVER['REQUEST_URI'], 'post.php') || strpos($_SERVER['REQUEST_URI'], 'post-new.php') || strpos($_SERVER['REQUEST_URI'], 'page-new.php') || strpos($_SERVER['REQUEST_URI'], 'page.php')) {
		
			//wp_enqueue_script only works  in => 'init'(for all), 'template_redirect'(for only public) , 'admin_print_scripts' for admin only
			if (function_exists('wp_enqueue_script')) {
				$jspath='/'. PLUGINDIR  . '/'. $this->plugin_folder.'/jqModal/jqModal.js';
				wp_enqueue_script('jqmodal_hana', $jspath, array('jquery'));
			}

		}
	}

	function print_javascript () {

 
?>
   <!--  for popup dialog -->
   
   <link href="<?php echo $this->plugin_url . '/jqModal/jqModal.css'; ?>" type="text/css" rel="stylesheet" />

   <script type="text/javascript">
    function click_hana_code_btn(){
    	jQuery('#dialog_hanacode').jqmShow();
    }
	
  	jQuery(document).ready(function(){
		// Add the buttons to the HTML view
	    if (QTags && typeof QTags.addButton == 'function' ) { // WP 3.3+
			QTags.addButton('hana_code_btn','Hana Code',click_hana_code_btn);
			
	    }else{ // Previous WP versions
			jQuery("#ed_toolbar").append('<input type=\"button\" class=\"ed_button\" onclick=\"jQuery(\'#dialog_hanacode\').jqmShow();\" title=\"Hana Code Insert\" value=\"Hana Code\" />');
		}
  	});
	    	
   	 

	jQuery(document).ready(function () {
		jQuery('#dialog_hanacode').jqm();
	});

	function update_hanacodeinsert(){
		var hci_select = document.getElementById("hci_select");
		if (hci_select) {
			key=hci_select.options[hci_select.selectedIndex].value;
			if (key.length > 0 ){
				text = "[hana-code-insert name='"+key+"' /]";				
				if ( typeof tinyMCE != 'undefined' && ( ed = tinyMCE.activeEditor ) && !ed.isHidden() ) {
					ed.focus();
					if (tinymce.isIE)
						ed.selection.moveToBookmark(tinymce.EditorManager.activeEditor.windowManager.bookmark);

					ed.execCommand('mceInsertContent', false, text);
				} else
					edInsertContent(edCanvas, text);
				 
			}
		}
		
		jQuery('#dialog_hanacode').jqmHide();
	}

	
   	</script>

	<div id="dialog_hanacode" class='jqmWindow'  >
	<div style='width:100%;text-align:center'>
	<h3>Hana Code Insert</h3>
	<a href='options-general.php?page=hana-code-insert/hana-code-insert.php' >Settings page</a><br />
	<?php 
	if (!$this->user_data) $this->load_user_data();
	$total=count($this->user_data);
	
	if ($total > 0):
	?>
	
	
	<select  id='hci_select' style='font-size:1.2em;'>
	<?php 
	
	
			for ($i=0;$i<$total ; $i++){
				$cur = $this->user_data[$i];	
				echo '<option value="'.$cur['name'].'">'.$cur['name'].'</option>';			
			}
	?>
	</select>
	<br />
		<input type='button' value='OK' onclick='update_hanacodeinsert()'; >
	
	<?php endif; ?>
	
	
		<input type='button' value='Cancel' onclick="jQuery('#dialog_hanacode').jqmHide();" >
	
	</div>
	
	</div>
	  
	
	<?php   
	  //end of print_javascript 
	}


}


//initialize hana code insert object
$hana_code = new hana_code_insert();
require_once("wpmarketing_feed.php");

 






