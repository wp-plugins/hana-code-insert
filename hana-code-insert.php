<?php
/*
Plugin Name: Hana Code Insert
Plugin URI: http://www.neox.net/w/2008/06/12/hana-code-insert-wordpress-plugin
Description: Easily insert any complicated HTML and JAVASCRIPT code or even custom PHP output in your Wordpress article. Useful for adding AdSense and Paypal donation code in the middle of the WP article.
Version: 1.6
Author: HanaDaddy
Author URI: http://www.neox.net
*/

 
class hana_code_insert
{
	//---------------------------------------------
	// variable that can be modified
	//---------------------------------------------
	var $eval_php=false;	//true : Activate the php evaluation, false: disable the php evaluation
	var $edit_col=80; 	//Textarea columns
	var $edit_row=5;  	//Textarea rows
	var $edit_wrap='on'; //off: wrap='off' , on : wrap='soft'
	//---------------------------------------------

	var $user_data;
	
	var $edit_wrap_str;
	var $tag_name="hana-code-insert";
	var $plugin_folder="hana-code-insert";
	var $plugin_url;
	
	var $admin_setting_menu='Hana Code Insert';
	var $admin_setting_title='Hana Code Insert Default Configuration';
	var $update_result='';
	var $error_result='';
	var $failed_entry;
	
	var $entity_target = array("&#8217;","&#8220;","&#8221;","&#038;","\'","&#8242;", "&#8216;");
	var $entity_replace= array("'",'"','"',"&","'","'","'");
	
  
	
					
	function hana_code_insert() {
		
		$this->plugin_url=get_bloginfo("wpurl") . "/wp-content/plugins/$this->plugin_folder";
		
		if ($this->edit_wrap=='off')
			$this->edit_wrap_str="wrap='off'";
		else
		if ($this->edit_wrap=='on')
			$this->edit_wrap_str="wrap='on'";
	}
	
	function load_user_data(){
		//load user data only if user_data is not loaded
		if (! $this->user_data ){
			$this->user_data = get_option('hanacode_options');		
			if (! $this->user_data )
				$this->user_data=array();
		}
	}

	function bind_hooks() {
		// third arg should be large value to execute in the later in the chain
		add_filter('the_content', array(&$this,'hana_code_return') , 100);
		add_action('admin_menu' , array(&$this,'hana_code_admin_menu') );
	}
	
	function hana_code_return($content) {
		return preg_replace_callback('|\['.$this->tag_name.'(.*?)/\]|ims', array(&$this,'hana_code_callback'), $content);
		 
	}
 
	function hana_code_admin_menu() {
		if ( function_exists('add_options_page') ) {
			add_options_page($this->admin_setting_title,$this->admin_setting_menu, 1, __FILE__,array(&$this,'hana_code_options_page'));

		}
	}
	
	function hana_code_callback($arg) {
		$this->load_user_data();
		//print($arg[1]);
		
		// hana_code_calback is called late to prevent HTML entities encoded(probably called in the early stage of chain).
		// but therefore, the input data is already encoded. So we need to convert back to original data.
		$fixed=str_replace($this->entity_target,$this->entity_replace,clean_pre($arg[1]));
		$attr_array=$this->parse_attributes($fixed);
	
		
		
		$key_list = array_keys($attr_array);
		
		if (! array_key_exists('name',$attr_array))	{
			return '<div style="color:#f00;font-weight:bold;">['.$this->tag_name.'] "name" attribute is mandatory. It must match one of your code items that you defined in the Hana Code Insert Settings</div>';	 	
		}
		
		$total=count($this->user_data);

		$found=null;
		for ($i=0;$i<$total ; $i++){
			$cur = $this->user_data[$i];	
			if ($cur['name'] == $attr_array['name']){
					$found=$cur;
					break;	
			}
		}

		
		$output='';
		if ($found){
			if ($found['php'] == '1' && $this->eval_php ){
				// need to insert that \n because of the possible use comment //
				$phpcode="ob_start(); ".$found['content'] . "\n \$hana_final_output = ob_get_contents(); ob_end_clean(); "; 
				eval($phpcode); //can be dangerous
				$output=$hana_final_output;
			}else{
				$output=$found['content'];			
			}
		}
		
		if ($output== '' && $cur['php'] != '1' ){
			$output= "<div style='color:#f00;font-weight:bold;'>[ ".$this->tag_name." ] '". $attr_array['name']."' is not found </div>";
			
		}
		//echo "[output] $output";
		
		return $output;				
			
	    
    }
	

    
	function hana_code_options_page() {
		$this->load_user_data();
		
		//global  $_POST;
		if ( $this->update_result != '' ) 
			print '<div id="message" class="updated fade"><p>' . $this->update_result . '</p></div>';
		if ( $this->error_result != '')
			print '<div id="message" class="error fade"><p>' . $this->error_result . '</p></div>';
					
	?>
<div class="wrap">
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
	If something goes wrong and you want to start fresh, click this button. It will erase all the entires. <input name="submit" value="Remove All" type="submit" />
	</form>
	
	<h3>New Entry</h3>
	<form action="" method="post">
	<input type='hidden' value='<?php print $this->tag_name; ?>' name='form_name'>
	<fieldset  class="options">
	<table id="optiontable" class="editform">
		<tr>
			<td valign="top" width='150'>New Entry Name:<br /><input type='text' name="new_name" value='<?php print $this->failed_entry['name']; ?>' size='15'>
			</td>
			<td>HTML code or Javascript or anything else you want to show in your article.<br />
			<textarea rows="<?php echo ($this->edit_row + 2); ?>" cols="<?php echo $this->edit_col ?>" name="new_content" <?php echo $this->edit_wrap_str; ?>><?php print $this->failed_entry['content']; ?></textarea><br>
				<?php if ($this->eval_php) : ?>
					<input type='checkbox' name='new_php' value='1' <?php if ($this->failed_entry['php'] == '1') { print "checked"; } ?> > Evaluate as php code.
				<?php endif ?>
			</td>
		</tr>
	</table>
	<p class="submit"><input name="submit" value="Create New Entry &raquo;" type="submit"></p>
	</fieldset>
	</form>
	
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
			<td><textarea rows='".$this->edit_row."' cols='".$this->edit_col."' name='update_content_$i' ". $this->edit_wrap_str .">".htmlspecialchars($cur['content'])."</textarea><br />";
		if ($this->eval_php) 
			print "	
				<input type='checkbox' name='update_php_$i' value='1' $php_checked > Evaluate as php code.<br />";
		else
			print "
				<input type='hidden' name='update_php_$i' value='".$cur['php']."'>";
		print"
			usage: <code>[".$this->tag_name." name='".$cur['name']."' /]</code>
			</td>
		</tr>\n";
	 } 
	 ?>	 
		
	</table>
	<p class="submit"><input name="submit" value="Update Entries &raquo;" type="submit"> 
	<input type='submit' name='submit' value='Delete &raquo;'></p>
	</fieldset>
	</form>

<a name="example" ></a>
   <p>
    <div><strong>Note:</strong>
Also, you can use PHP codes. If you enable the 'Evaluate as php code.' option, the code entry 
will be evaluated as php codes. The output string will be embeded in the middle of your WP article. 
However, this option is disabled by default since it can be dangerous. If you want to enable the option, 
you need to edit the <code><?php print __FILE__; ?></code> . 
Then, change <code>var $eval_php=false;</code> to <code>var $eval_php=true;</code>.

    
    </div>
    
	<pre style="padding: 10px; border:1px dotted black">
class hana_code_insert
{
	//---------------------------------------------
	// variable that can be modified
	//---------------------------------------------
	var $eval_php=<span style='color:#f00'>false</span>;	//Change this value to <span style='color:#00f'>true</span> to activate PHP eval.
	var $edit_col=80; 	//Textarea columns
	var $edit_row=5;  	//Textarea rows
	var $edit_wrap='on'; //off: wrap='off' , on : wrap='soft'
	//---------------------------------------------
	...
</pre>


 </p>


    <p>Thank you for using my plugin. - <a href='http://wwww.neox.net/'>HanaDaddy</a></p>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<input type="hidden" name="cmd" value="_donations">
<input type="hidden" name="business" value="hanadaddy@gmail.com">
<input type="hidden" name="item_name" value="HanaDaddy Donation - Thank you!">
<input type="hidden" name="no_shipping" value="0">
<input type="hidden" name="no_note" value="1">
<input type="hidden" name="currency_code" value="USD">
<input type="hidden" name="tax" value="0">
<input type="hidden" name="lc" value="US">

<input type="hidden" name="bn" value="PP-DonationsBF">
<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1"><br />
</form>
</div>

</div>

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
					if ($this->eval_php)
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
    

}

$hana_code = new hana_code_insert();

$hana_code->bind_hooks();



if ($_POST['form_name'] == $hana_code->tag_name) {
	// admin option page update
	if ( isset($_POST['new_name']) ) {
		$hana_code->hana_code_options_new();
	}

	if ( substr($_POST['submit'],0,10) == 'Remove All'){
		//print "haha-deleting";
		$hana_code->hana_code_options_delete_all();	
	}else
	if ( substr($_POST['submit'],0,6) == 'Delete'){
		//print "haha-deleting";
		$hana_code->hana_code_options_delete();	
	}else
	if ( substr($_POST['submit'],0,6) == 'Update'){
		$hana_code->hana_code_options_update();
	}
}

?>