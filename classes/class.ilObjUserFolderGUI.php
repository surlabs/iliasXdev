<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2001 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/


/**
* Class ilObjUserFolderGUI
*
* @author Stefan Meyer <smeyer@databay.de> 
* $Id$
* 
* @extends ilObjectGUI
* @package ilias-core
*/

require_once "class.ilObjectGUI.php";

class ilObjUserFolderGUI extends ilObjectGUI
{
	var $ctrl;

	/**
	* Constructor
	* @access public
	*/
	function ilObjUserFolderGUI($a_data,$a_id,$a_call_by_reference, $a_prepare_output = true)
	{
		global $ilCtrl;

		define('USER_FOLDER_ID',7);

		$this->ctrl =& $ilCtrl;

		$this->type = "usrf";

		$this->ilObjectGUI($a_data,$a_id,$a_call_by_reference,$a_prepare_output);
				
	}

	function &executeCommand()
	{
		global $rbacsystem;

		$next_class = $this->ctrl->getNextClass($this);
		$cmd = $this->ctrl->getCmd();
		switch($next_class)
		{
			default:
				if(!$cmd)
				{
					$cmd = "view";
				}
				$cmd .= "Object";
				$this->$cmd();
					
				break;
		}
		return true;
	}

	/**
	* list users
	*
	* @access	public
	*/
	function viewObject()
	{
		global $rbacsystem;

		$_SESSION["user_filter"] = (isset($_POST["user_filter"]))?$_POST["user_filter"]:"1";
		
		if (!$rbacsystem->checkAccess("visible,read",$this->object->getRefId()))
		{
			$this->ilias->raiseError($this->lng->txt("permission_denied"),$this->ilias->error_obj->MESSAGE);
		}

		//prepare objectlist
		$this->data = array();
		$this->data["data"] = array();
		$this->data["ctrl"] = array();

		$this->data["cols"] = array("", "login", "firstname", "lastname", "email");
		
		$usr_data = ilObjUser::_getAllUserData(array("login","firstname","lastname","email"), $_SESSION["user_filter"]);

		foreach ($usr_data as $val)
		{
			if ($val["usr_id"] == ANONYMOUS_USER_ID)
			{
                continue;
            }

			//visible data part
			$this->data["data"][] = array(
							"login"			=> $val["login"],
							"firstname"		=> $val["firstname"],
							"lastname"		=> $val["lastname"],
							"email"			=> $val["email"],
							"obj_id"		=> $val["usr_id"]
						);
		}
		
		$this->maxcount = count($this->data["data"]);
		// TODO: correct this in objectGUI
		if ($_GET["sort_by"] == "name")
		{
			$_GET["sort_by"] = "login";
		}
		
		// sorting array
		$this->data["data"] = ilUtil::sortArray($this->data["data"],$_GET["sort_by"],$_GET["sort_order"]);
		$this->data["data"] = array_slice($this->data["data"],$_GET["offset"],$_GET["limit"]);

		// now compute control information
		foreach ($this->data["data"] as $key => $val)
		{
			$this->data["ctrl"][$key] = array(
												"ref_id"	=> $this->id,
												"obj_id"	=> $val["obj_id"]
											);
			$tmp[] = $val["obj_id"];
			unset($this->data["data"][$key]["obj_id"]);
		}

		//add template for buttons
		$this->tpl->addBlockfile("BUTTONS", "buttons", "tpl.buttons.html");

		// display button
		$this->tpl->setCurrentBlock("btn_cell");
		$this->tpl->setVariable("BTN_LINK","adm_object.php?ref_id=".$this->ref_id.$obj_str."&cmd=searchUserForm");
		$this->tpl->setVariable("BTN_TXT",$this->lng->txt("search_user"));
		$this->tpl->parseCurrentBlock();

		if (AUTH_CURRENT == AUTH_LOCAL)
		{
			$this->tpl->setCurrentBlock("btn_cell");
			$this->tpl->setVariable("BTN_LINK", "adm_object.php?ref_id=".$this->ref_id.$obj_str."&cmd=importUserForm");
			$this->tpl->setVariable("BTN_TXT", $this->lng->txt("import_users"));
			$this->tpl->parseCurrentBlock();
		}
					    

		$this->tpl->addBlockfile("ADM_CONTENT", "adm_content", "tpl.usr_list.html");
		
		$this->tpl->setCurrentBlock("filter");
		$this->tpl->setVariable("FILTER_TXT_FILTER",$this->lng->txt('filter'));
		$this->tpl->setVariable("SELECT_FILTER",$this->__buildUserFilterSelect());
		$this->tpl->setVariable("FILTER_ACTION",$this->ctrl->getFormAction($this));
		$this->tpl->setVariable("FILTER_NAME",'view');
		$this->tpl->setVariable("FILTER_VALUE",$this->lng->txt('apply_filter'));
		$this->tpl->parseCurrentBlock();
		
		/*$this->tpl->addBlockfile("FILTER", "filter", "tpl.usr_filter.html");
		
		$this->tpl->setVariable("FORM_ACTION", "adm_object.php?ref_id=".$this->ref_id.$obj_str."&cmd=importUserForm");
		$this->tpl->setVariable("FILTER_SELECT", $this->__buildUserFilterSelect());
		$this->tpl->setVariable("BTN_SET_TXT", $this->lng->txt("set"));*/

		$this->displayList();
	} //function


	/**
	* display object list
	*
	* @access	public
 	*/
	function displayList()
	{
		include_once "./classes/class.ilTableGUI.php";

		
		// load template for table
		$this->tpl->addBlockfile("USR_TABLE", "user_table", "tpl.table.html");
		// load template for table content data
		$this->tpl->addBlockfile("TBL_CONTENT", "tbl_content", "tpl.obj_tbl_rows.html");

		$num = 0;

		$obj_str = ($this->call_by_reference) ? "" : "&obj_id=".$this->obj_id;
		$this->tpl->setVariable("FORMACTION", "adm_object.php?ref_id=".$this->ref_id."$obj_str&cmd=gateway");

		// create table
		$tbl = new ilTableGUI();

		// title & header columns
		$tbl->setTitle($this->object->getTitle(),"icon_".$this->object->getType()."_b.gif",
					   $this->lng->txt("obj_".$this->object->getType()));
		//$tbl->setHelp("tbl_help.php","icon_help.gif",$this->lng->txt("help"));

		foreach ($this->data["cols"] as $val)
		{
			$header_names[] = $this->lng->txt($val);
		}

		$tbl->setHeaderNames($header_names);

		$header_params = array("ref_id" => $this->ref_id);
		$tbl->setHeaderVars($this->data["cols"],$header_params);
		$tbl->setColumnWidth(array("","25%","25$%","25%","25%"));
		

		// control
        //$tbl->enable("hits");
		$tbl->setOrderColumn($_GET["sort_by"]);
		$tbl->setOrderDirection($_GET["sort_order"]);
		$tbl->setLimit($_GET["limit"]);
		$tbl->setOffset($_GET["offset"]);
		$tbl->setMaxCount($this->maxcount);

		if (AUTH_CURRENT != AUTH_LOCAL)
		{
			$this->showActions(false);
		}
		else
		{
			$this->showActions(true);
		}
		
		// footer
		$tbl->setFooter("tblfooter",$this->lng->txt("previous"),$this->lng->txt("next"));
		#$tbl->disable("footer");

		// render table
		$tbl->render();
		
		if (is_array($this->data["data"][0]))
		{
			//table cell
			for ($i=0; $i < count($this->data["data"]); $i++)
			{
				$data = $this->data["data"][$i];
				$ctrl = $this->data["ctrl"][$i];

				// color changing
				$css_row = ilUtil::switchColor($i+1,"tblrow1","tblrow2");

				$this->tpl->setCurrentBlock("checkbox");
				$this->tpl->setVariable("CHECKBOX_ID", $ctrl["obj_id"]);
				//$this->tpl->setVariable("CHECKED", $checked);
				$this->tpl->setVariable("CSS_ROW", $css_row);
				$this->tpl->parseCurrentBlock();

				$this->tpl->setCurrentBlock("table_cell");
				$this->tpl->setVariable("CELLSTYLE", "tblrow1");
				$this->tpl->parseCurrentBlock();

				foreach ($data as $key => $val)
				{
					//build link
					$link = "adm_object.php?ref_id=7&obj_id=".$ctrl["obj_id"];

					if ($key == "login")
					{
						$this->tpl->setCurrentBlock("begin_link");
						$this->tpl->setVariable("LINK_TARGET", $link);
						$this->tpl->parseCurrentBlock();
						$this->tpl->touchBlock("end_link");
					}

					$this->tpl->setCurrentBlock("text");
					$this->tpl->setVariable("TEXT_CONTENT", $val);
					$this->tpl->parseCurrentBlock();
					$this->tpl->setCurrentBlock("table_cell");
					$this->tpl->parseCurrentBlock();
				} //foreach

				$this->tpl->setCurrentBlock("tbl_content");
				$this->tpl->setVariable("CSS_ROW", $css_row);
				$this->tpl->parseCurrentBlock();
			} //for
		}



	}
	
	/**
	* show possible action (form buttons)
	*
	* @param	boolean
	* @access	public
 	*/
	function showActions($with_subobjects = false)
	{
		global $rbacsystem;

		$operations = array();

		if ($this->actions == "")
		{
			$d = $this->objDefinition->getActions($_GET["type"]);
		}
		else
		{
			$d = $this->actions;
		}

		foreach ($d as $row)
		{
			if ($rbacsystem->checkAccess($row["name"],$this->object->getRefId()))
			{
				$operations[] = $row;
			}
		}

		if (count($operations) > 0)
		{
			foreach ($operations as $val)
			{
				$this->tpl->setCurrentBlock("tbl_action_btn");
				$this->tpl->setVariable("IMG_ARROW", ilUtil::getImagePath("arrow_downright.gif"));
				$this->tpl->setVariable("BTN_NAME", $val["name"]);
				$this->tpl->setVariable("BTN_VALUE", $this->lng->txt($val["lng"]));
				$this->tpl->parseCurrentBlock();
			}
		}

		if ($with_subobjects === true)
		{
			$subobjs = $this->showPossibleSubObjects();
		}

		if ((count($operations) > 0) or $subobjs === true)
		{
			$this->tpl->setCurrentBlock("tbl_action_row");
			$this->tpl->setVariable("COLUMN_COUNTS",count($this->data["cols"]));
			$this->tpl->parseCurrentBlock();
		}
	}

	/**
	* show possible subobjects (pulldown menu)
	* overwritten to prevent displaying of role templates in local role folders
	*
	* @access	public
 	*/
	function showPossibleSubObjects()
	{
		global $rbacsystem;

		$d = $this->objDefinition->getCreatableSubObjects($this->object->getType());
		
		if (!$rbacsystem->checkAccess('create_user',$this->object->getRefId()))
		{
			unset($d["usr"]);			
		}

		if (count($d) > 0)
		{
			foreach ($d as $row)
			{
			    $count = 0;
				if ($row["max"] > 0)
				{
					//how many elements are present?
					for ($i=0; $i<count($this->data["ctrl"]); $i++)
					{
						if ($this->data["ctrl"][$i]["type"] == $row["name"])
						{
						    $count++;
						}
					}
				}
				if ($row["max"] == "" || $count < $row["max"])
				{
					$subobj[] = $row["name"];
				}
			}
		}

		if (is_array($subobj))
		{
			//build form
			$opts = ilUtil::formSelect(12,"new_type",$subobj);
			$this->tpl->setCurrentBlock("add_object");
			$this->tpl->setVariable("SELECT_OBJTYPE", $opts);
			$this->tpl->setVariable("BTN_NAME", "create");
			$this->tpl->setVariable("TXT_ADD", $this->lng->txt("add"));
			$this->tpl->parseCurrentBlock();
			
			return true;
		}

		return false;
	}

	/**
	* confirmObject
	*
	* @access	public
	*/
	function confirmedDeleteObject()
	{
		global $rbacsystem;

		// FOR NON_REF_OBJECTS WE CHECK ACCESS ONLY OF PARENT OBJECT ONCE
		if (!$rbacsystem->checkAccess('delete',$this->object->getRefId()))
		{
			$this->ilias->raiseError($this->lng->txt("msg_no_perm_delete"),$this->ilias->error_obj->WARNING);
		}

		if (in_array($_SESSION["AccountId"],$_SESSION["saved_post"]))
		{
			$this->ilias->raiseError($this->lng->txt("msg_no_delete_yourself"),$this->ilias->error_obj->WARNING);
		}

		// FOR ALL SELECTED OBJECTS
		foreach ($_SESSION["saved_post"] as $id)
		{
			// instatiate correct object class (usr)
			$obj =& $this->ilias->obj_factory->getInstanceByObjId($id);
			$obj->delete();
		}

		// Feedback
		sendInfo($this->lng->txt("user_deleted"),true);

		ilUtil::redirect("adm_object.php?ref_id=".$_GET["ref_id"]);
	}

	/**
	* display deletion confirmation screen
	*/
	function deleteObject()
	{
		if(!isset($_POST["id"]))
		{
			$this->ilias->raiseError($this->lng->txt("no_checkbox"),$this->ilias->error_obj->MESSAGE);
		}
		// SAVE POST VALUES
		$_SESSION["saved_post"] = $_POST["id"];

		unset($this->data);
		$this->data["cols"] = array("type", "title", "description", "last_change");

		foreach($_POST["id"] as $id)
		{
			$obj_data =& $this->ilias->obj_factory->getInstanceByObjId($id);

			$this->data["data"]["$id"] = array(
				"type"        => $obj_data->getType(),
				"title"       => $obj_data->getTitle(),
				"desc"        => $obj_data->getDescription(),
				"last_update" => $obj_data->getLastUpdateDate());
		}

		$this->data["buttons"] = array( "cancelDelete"  => $this->lng->txt("cancel"),
								  "confirmedDelete"  => $this->lng->txt("confirm"));

		$this->getTemplateFile("confirm");

		sendInfo($this->lng->txt("info_delete_sure"));

		$this->tpl->setVariable("FORMACTION", "adm_object.php?ref_id=".$_GET["ref_id"]."&cmd=gateway");

		// BEGIN TABLE HEADER
		foreach ($this->data["cols"] as $key)
		{
			$this->tpl->setCurrentBlock("table_header");
			$this->tpl->setVariable("TEXT",$this->lng->txt($key));
			$this->tpl->parseCurrentBlock();
		}
		// END TABLE HEADER

		// BEGIN TABLE DATA
		$counter = 0;

		foreach($this->data["data"] as $key => $value)
		{
			// BEGIN TABLE CELL
			foreach($value as $key => $cell_data)
			{
				$this->tpl->setCurrentBlock("table_cell");

				// CREATE TEXT STRING
				if($key == "type")
				{
					$this->tpl->setVariable("TEXT_CONTENT",ilUtil::getImageTagByType($cell_data,$this->tpl->tplPath));
				}
				else
				{
					$this->tpl->setVariable("TEXT_CONTENT",$cell_data);
				}
				$this->tpl->parseCurrentBlock();
			}

			$this->tpl->setCurrentBlock("table_row");
			$this->tpl->setVariable("CSS_ROW",ilUtil::switchColor(++$counter,"tblrow1","tblrow2"));
			$this->tpl->parseCurrentBlock();
			// END TABLE CELL
		}
		// END TABLE DATA

		// BEGIN OPERATION_BTN
		foreach($this->data["buttons"] as $name => $value)
		{
			$this->tpl->setCurrentBlock("operation_btn");
			$this->tpl->setVariable("BTN_NAME",$name);
			$this->tpl->setVariable("BTN_VALUE",$value);
			$this->tpl->parseCurrentBlock();
		}
	}
	
	/**
     * displays user search form
     *
     *
     */
	function searchUserFormObject ()
	{
		$this->tpl->addBlockfile("ADM_CONTENT", "adm_content", "tpl.usr_search_form.html");

		$this->tpl->setVariable("FORMACTION", "adm_object.php?ref_id=".$this->ref_id."&cmd=gateway");
		$this->tpl->setVariable("USERNAME_CHECKED", " checked=\"checked\"");
		$this->tpl->setVariable("FIRSTNAME_CHECKED", " checked=\"checked\"");
		$this->tpl->setVariable("LASTNAME_CHECKED", " checked=\"checked\"");
		$this->tpl->setVariable("EMAIL_CHECKED", " checked=\"checked\"");
		$this->tpl->setVariable("ACTIVE_CHECKED", " checked=\"checked\"");
		$this->tpl->setVariable("INACTIVE_CHECKED", " checked=\"checked\"");
		$this->tpl->setVariable("TXT_SEARCH_USER",$this->lng->txt("search_user"));
		$this->tpl->setVariable("TXT_SEARCH_IN",$this->lng->txt("search_in"));
		$this->tpl->setVariable("TXT_SEARCH_USERNAME",$this->lng->txt("username"));
		$this->tpl->setVariable("TXT_SEARCH_FIRSTNAME",$this->lng->txt("firstname"));
		$this->tpl->setVariable("TXT_SEARCH_LASTNAME",$this->lng->txt("lastname"));
		$this->tpl->setVariable("TXT_SEARCH_EMAIL",$this->lng->txt("email"));
        $this->tpl->setVariable("TXT_SEARCH_ACTIVE",$this->lng->txt("search_active"));
        $this->tpl->setVariable("TXT_SEARCH_INACTIVE",$this->lng->txt("search_inactive"));
		$this->tpl->setVariable("BUTTON_SEARCH",$this->lng->txt("search"));
		$this->tpl->setVariable("BUTTON_CANCEL",$this->lng->txt("cancel"));
        $this->tpl->setVariable("TXT_SEARCH_NOTE",$this->lng->txt("search_note"));
	}

	function searchCancelledObject()
	{
		sendInfo($this->lng->txt("action_aborted"),true);

		header("Location: adm_object.php?ref_id=".$_GET["ref_id"]."&cmd=gateway");
		exit();
	}

	function searchUserObject()
	{
		global $rbacreview;

		$obj_str = "&obj_id=".$this->obj_id;

		$_POST["search_string"] = $_POST["search_string"] ? $_POST["search_string"] : urldecode($_GET["search_string"]);
        $_POST["search_fields"] = $_POST["search_fields"] ? $_POST["search_fields"] : array();

        if (empty($_POST["search_string"]))
        {
            $_POST["search_string"] = "%";
        }

		if (count($search_result = ilObjUser::searchUsers($_POST["search_string"])) == 0)
		{
			sendInfo($this->lng->txt("msg_no_search_result")." ".$this->lng->txt("with")." '".htmlspecialchars($_POST["search_string"])."'",true);

			header("Location: adm_object.php?ref_id=".$_GET["ref_id"]."&cmd=searchUserForm");
			exit();		
		}
		//add template for buttons
		$this->tpl->addBlockfile("BUTTONS", "buttons", "tpl.buttons.html");
		
		// display button
		$this->tpl->setCurrentBlock("btn_cell");
		$this->tpl->setVariable("BTN_LINK","adm_object.php?ref_id=".$this->ref_id."&cmd=searchUserForm");
		$this->tpl->setVariable("BTN_TXT",$this->lng->txt("search_new"));
		$this->tpl->parseCurrentBlock();

        $this->data["cols"] = array("", "login", "firstname", "lastname", "email", "active");

        if (in_array("active", $_POST["search_fields"]))
        {
            $searchActive = true;
        }
        if (in_array("inactive", $_POST["search_fields"]))
        {
            $searchInactive = true;
        }

		foreach ($search_result as $key => $val)
		{
            $val["active_text"] = $this->lng->txt("inactive");
            if ($val["active"])
            {
                $val["active_text"] = $this->lng->txt("active");
            }

						// check if the fields are set
						$searchStringToLower = strtolower($_POST["search_string"]);
						$displaySearchResult = false;
						if (in_array("username", $_POST["search_fields"]))
							if (strpos(strtolower($val["login"]), strtolower($_POST["search_string"])) !== false)
								$displaySearchResult = true;
						if (in_array("firstname", $_POST["search_fields"]))
							if (strpos(strtolower($val["firstname"]), strtolower($_POST["search_string"])) !== false)
								$displaySearchResult = true;
						if (in_array("lastname", $_POST["search_fields"]))
							if (strpos(strtolower($val["lastname"]), strtolower($_POST["search_string"])) !== false)
								$displaySearchResult = true;
						if (in_array("email", $_POST["search_fields"]))
							if (strpos(strtolower($val["email"]), strtolower($_POST["search_string"])) !== false)
								$displaySearchResult = true;
						if (($val["active"] == 1) && ($searchActive == true) ||
                    ($val["active"] == 0) && ($searchInactive == true))
            {
								if ((strcmp($_POST["search_string"], "%") == 0) || $displaySearchResult)
								{
									//visible data part
									$this->data["data"][] = array(
													"login"         => $val["login"],
													"firstname"     => $val["firstname"],
													"lastname"      => $val["lastname"],
													"email"         => $val["email"],
													"active"        => $val["active_text"],
													"obj_id"        => $val["usr_id"]
													);
								}
            }
		}
		if (count($this->data["data"]) == 0)
		{
			sendInfo($this->lng->txt("msg_no_search_result")." ".$this->lng->txt("with")." '".htmlspecialchars($_POST["search_string"])."'",true);

			header("Location: adm_object.php?ref_id=".$_GET["ref_id"]."&cmd=searchUserForm");
			exit();		
		}
		
		$this->maxcount = count($this->data["data"]);

		// TODO: correct this in objectGUI
		if ($_GET["sort_by"] == "name")
		{
			$_GET["sort_by"] = "login";
		}

		// sorting array
		$this->data["data"] = ilUtil::sortArray($this->data["data"],$_GET["sort_by"],$_GET["sort_order"]);
		$this->data["data"] = array_slice($this->data["data"],$_GET["offset"],$_GET["limit"]);

		// now compute control information
		foreach ($this->data["data"] as $key => $val)
		{
			$this->data["ctrl"][$key] = array(
												"ref_id"	=> $this->id,
												"obj_id"	=> $val["obj_id"]
											);
			$tmp[] = $val["obj_id"];
			unset($this->data["data"][$key]["obj_id"]);
		}

		// remember filtered users
		$_SESSION["user_list"] = $tmp;		
	
		// load template for table
		$this->tpl->addBlockfile("ADM_CONTENT", "adm_content", "tpl.table.html");
		// load template for table content data
		$this->tpl->addBlockfile("TBL_CONTENT", "tbl_content", "tpl.obj_tbl_rows.html");

		$num = 0;

		$this->tpl->setVariable("FORMACTION", "adm_object.php?ref_id=".$this->ref_id."&cmd=gateway&sort_by=name&sort_order=".$_GET["sort_order"]."&offset=".$_GET["offset"]);

		// create table
		include_once "./classes/class.ilTableGUI.php";
		$tbl = new ilTableGUI();

		// title & header columns
		$tbl->setTitle($this->lng->txt("search_result"),"icon_".$this->object->getType()."_b.gif",$this->lng->txt("obj_".$this->object->getType()));
		$tbl->setHelp("tbl_help.php","icon_help.gif",$this->lng->txt("help"));
		
		foreach ($this->data["cols"] as $val)
		{
			$header_names[] = $this->lng->txt($val);
		}
		
		$tbl->setHeaderNames($header_names);

		$header_params = array(
							"ref_id"		=> $this->ref_id,
							"cmd"			=> "searchUser",
							"search_string" => urlencode($_POST["search_string"])
					  		);

		$tbl->setHeaderVars($this->data["cols"],$header_params);
		$tbl->setColumnWidth(array("","25%","25$%","25%","25%"));

		// control
        $tbl->enable("hits");
		$tbl->setOrderColumn($_GET["sort_by"]);
		$tbl->setOrderDirection($_GET["sort_order"]);
		$tbl->setLimit($_GET["limit"]);
		$tbl->setOffset($_GET["offset"]);
		$tbl->setMaxCount($this->maxcount);

		$this->tpl->setVariable("COLUMN_COUNTS",count($this->data["cols"]));	

		$this->showActions(true);
		
		// footer
		$tbl->setFooter("tblfooter",$this->lng->txt("previous"),$this->lng->txt("next"));

		// render table
		$tbl->render();

		if (is_array($this->data["data"][0]))
		{
			//table cell
			for ($i=0; $i < count($this->data["data"]); $i++)
			{
				$data = $this->data["data"][$i];
				$ctrl = $this->data["ctrl"][$i];

				// color changing
				$css_row = ilUtil::switchColor($i+1,"tblrow1","tblrow2");

				$this->tpl->setCurrentBlock("checkbox");
				$this->tpl->setVariable("CHECKBOX_ID", $ctrl["obj_id"]);
				//$this->tpl->setVariable("CHECKED", $checked);
				$this->tpl->setVariable("CSS_ROW", $css_row);
				$this->tpl->parseCurrentBlock();

				$this->tpl->setCurrentBlock("table_cell");
				$this->tpl->setVariable("CELLSTYLE", "tblrow1");
				$this->tpl->parseCurrentBlock();

				foreach ($data as $key => $val)
				{
					//build link
					$link = "adm_object.php?ref_id=7&obj_id=".$ctrl["obj_id"];

					if ($key == "login")
					{
						$this->tpl->setCurrentBlock("begin_link");
						$this->tpl->setVariable("LINK_TARGET", $link);
						$this->tpl->parseCurrentBlock();
						$this->tpl->touchBlock("end_link");
					}

					$this->tpl->setCurrentBlock("text");
					$this->tpl->setVariable("TEXT_CONTENT", $val);
					$this->tpl->parseCurrentBlock();
					$this->tpl->setCurrentBlock("table_cell");
					$this->tpl->parseCurrentBlock();
				} //foreach

				$this->tpl->setCurrentBlock("tbl_content");
				$this->tpl->setVariable("CSS_ROW", $css_row);
				$this->tpl->parseCurrentBlock();
			} //for
		}
	}

	/**
	* display form for user import
	*/
	function importUserFormObject ()
	{
		$this->tpl->addBlockfile("ADM_CONTENT", "adm_content", "tpl.usr_import_form.html");

		//$this->tpl->setVariable("FORMACTION", "adm_object.php?ref_id=".$this->ref_id."&cmd=gateway");
		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormaction($this));

		$this->tpl->setVariable("TXT_IMPORT_USERS", $this->lng->txt("import_users"));
		$this->tpl->setVariable("TXT_IMPORT_FILE", $this->lng->txt("import_file"));
		$this->tpl->setVariable("TXT_IMPORT_ROOT_USER", $this->lng->txt("import_root_user"));

		$this->tpl->setVariable("BTN_IMPORT", $this->lng->txt("import"));
		$this->tpl->setVariable("BTN_CANCEL", $this->lng->txt("cancel"));
	}


	/**
	* import cancelled
	*
	* @access private
	*/
	function importCancelledObject()
	{

		sendInfo($this->lng->txt("msg_cancel"),true);

		if($this->ctrl->getTargetScript() == 'adm_object.php')
		{
			$return_location = $_GET["cmd_return_location"];
			ilUtil::redirect($this->ctrl->getLinkTarget($this,$return_location));
		}
		else
		{
			$this->ctrl->redirectByClass('ilobjcategorygui','listUsers');
		}
	}

	/**
	* get user import directory name
	*/
	function getImportDir()
	{
		// For each user a different directory must be used to prevent
		// that one user overwrites the import data that another user is
		// currently importing.
		global $ilUser;
		ilUtil::makeDir(ilUtil::getDataDir()."/user_import");
		return ilUtil::getDataDir()."/user_import/usr_".$ilUser->getId();
	}

	/**
	* display form for user import
	*/
	function importUserRoleAssignmentObject ()
	{
		include_once './classes/class.ilObjRole.php';
		include_once './classes/class.ilUserImportParser.php';
		
		global $rbacreview, $rbacsystem, $tree, $lng;
		

		$this->tpl->addBlockfile("ADM_CONTENT", "adm_content", "tpl.usr_import_roles.html");

		$import_dir = $this->getImportDir();

		// recreate user import directory
		if (@is_dir($import_dir))
		{
			ilUtil::delDir($import_dir);
		}
		ilUtil::makeDir($import_dir);

		// move uploaded file to user import directory
		$file_name = $_FILES["importFile"]["name"];
		$parts = pathinfo($file_name);
		$full_path = $import_dir."/".$file_name;

		// check if import file exists
		if (!is_file($_FILES["importFile"]["tmp_name"]))
		{
			$this->ilias->raiseError($this->lng->txt("no_import_file_found")
				, $this->ilias->error_obj->MESSAGE);
		}
		ilUtil::moveUploadedFile($_FILES["importFile"]["tmp_name"],
			$_FILES["importFile"]["name"], $full_path);

		// handle zip file		
		if (strtolower($parts["extension"]) == "zip")
		{
			// unzip file
			ilUtil::unzip($full_path);

			$xml_file = null;
			$file_list = ilUtil::getDir($import_dir);
			foreach ($file_list as $a_file)
			{
				if (substr($a_file['entry'],-4) == '.xml')
				{
					$xml_file = $import_dir."/".$a_file['entry'];
					break;
				}
			}
			if (is_null($xml_file))
			{
				$subdir = basename($parts["basename"],".".$parts["extension"]);
				$xml_file = $import_dir."/".$subdir."/".$subdir.".xml";
			}
		}
		// handle xml file
		else
		{
			$xml_file = $full_path;
		}

		// check xml file		
		if (!is_file($xml_file))
		{
			$this->ilias->raiseError($this->lng->txt("no_xml_file_found_in_zip")
				." ".$subdir."/".$subdir.".xml", $this->ilias->error_obj->MESSAGE);
		}

		require_once("classes/class.ilUserImportParser.php");

		// Verify the data
		// ---------------
		$importParser = new ilUserImportParser($xml_file, IL_VERIFY);
		$importParser->startParsing();

		switch ($importParser->getErrorLevel())
		{
			case IL_IMPORT_SUCCESS :
				break;
			case IL_IMPORT_WARNING :
				$this->tpl->setVariable("IMPORT_LOG", $importParser->getProtocolAsHTML($lng->txt("verification_warning_log")));
				break;
			case IL_IMPORT_FAILURE :
				$this->ilias->raiseError(
					$lng->txt("verification_failed").$importParser->getProtocolAsHTML($lng->txt("verification_failure_log")),
					$this->ilias->error_obj->MESSAGE
				);
				return;
		}

		// Create the role selection form
		// ------------------------------
		$this->tpl->setCurrentBlock("role_selection_form");
		$this->tpl->setVariable("FORMACTION",$this->ctrl->getFormAction($this));
		$this->tpl->setVariable("TXT_ROLE_ASSIGNMENT", $this->lng->txt("role_assignment"));
		$this->tpl->setVariable("BTN_IMPORT", $this->lng->txt("import"));
		$this->tpl->setVariable("BTN_CANCEL", $this->lng->txt("cancel"));
		$this->tpl->setVariable("XML_FILE_NAME", $xml_file);

		// Extract the roles
		$importParser = new ilUserImportParser($xml_file, IL_EXTRACT_ROLES);
		$importParser->startParsing();
		$roles = $importParser->getCollectedRoles();

		// get global roles
		$all_gl_roles = $rbacreview->getRoleListByObject(ROLE_FOLDER_ID);
		$gl_roles = array();
		foreach ($all_gl_roles as $obj_data)
		{
			// check assignment permission if called from local admin
			if($this->object->getRefId() != USER_FOLDER_ID and !in_array(SYSTEM_ROLE_ID,$_SESSION["RoleId"]))
			{
				if(!ilObjRole::_getAssignUsersStatus($obj_data['obj_id']))
				{
					continue;
				}
			}
			// exclude anonymous role from list
			if ($obj_data["obj_id"] != ANONYMOUS_ROLE_ID)
			{
				// do not allow to assign users to administrator role if current user does not has SYSTEM_ROLE_ID
				if ($obj_data["obj_id"] != SYSTEM_ROLE_ID or in_array(SYSTEM_ROLE_ID,$_SESSION["RoleId"]))
				{
					$gl_roles[$obj_data["obj_id"]] = $obj_data["title"];
				}
			}
		}

		// global roles
		$got_globals = false;
		foreach($roles as $role_id => $role)
		{
			if ($role["type"] == "Global")
			{
				if (! $got_globals)
				{
					$got_globals = true;

					$this->tpl->setCurrentBlock("global_role_section");
					$this->tpl->setVariable("TXT_GLOBAL_ROLES_IMPORT", $this->lng->txt("roles_of_import_global"));
					$this->tpl->setVariable("TXT_GLOBAL_ROLES", $this->lng->txt("assign_global_role"));
				}

				// pre selection for role
				$pre_select = array_search($role[name], $gl_roles);
				if (! $pre_select)
				{
					switch($role["name"])
					{
						case "Administrator":	// ILIAS 2/3 Administrator
							$pre_select = array_search("Administrator", $gl_roles);
							break;

						case "Autor":			// ILIAS 2 Author
							$pre_select = array_search("User", $gl_roles);
							break;

						case "Lerner":			// ILIAS 2 Learner
							$pre_select = array_search("User", $gl_roles);
							break;

						case "Gast":			// ILIAS 2 Guest
							$pre_select = array_search("Guest", $gl_roles);
							break;

						default:
							$pre_select = array_search("User", $gl_roles);
							break;
					}
				}
				$this->tpl->setCurrentBlock("global_role");
				$role_select = ilUtil::formSelect($pre_select, "role_assign[".$role_id."]", $gl_roles, false, true);
				$this->tpl->setVariable("TXT_IMPORT_GLOBAL_ROLE", $role["name"]." [".$role_id."]");
				$this->tpl->setVariable("SELECT_GLOBAL_ROLE", $role_select);
				$this->tpl->parseCurrentBlock();
			}
		}

		// get local roles
		$loc_roles = $rbacreview->getAssignableRoles();
		$pre_select = null;
		$l_roles = array();
		foreach ($loc_roles as $key => $loc_role)
		{
				// fetch context path of role
				$rolf = $rbacreview->getFoldersAssignedToRole($loc_role["obj_id"],true);

				// only process role folders that are not set to status "deleted" 
				// and for which the user has write permissions.
				// We also don't show the roles which are in the ROLE_FOLDER_ID folder.
				// (The ROLE_FOLDER_ID folder contains the global roles).
				if (!$rbacreview->isDeleted($rolf[0])
				&& $rbacsystem->checkAccess('write',$tree->getParentId($rolf[0]))
				&& $rolf[0] != ROLE_FOLDER_ID
				)
				{
					$path = "";
					if ($this->tree->isInTree($rolf[0]))
					{
						// Create path. Paths which have more than 4 segments
						// are truncated in the mittle.
						$tmpPath = $this->tree->getPathFull($rolf[0]);
						for ($i = 1, $n = count($tmpPath) - 1; $i < $n; $i++)
						{
							if ($i > 1)
							{
								$path = $path.' > ';
							}
							if ($i < 3 || $i > $n - 3)
							{
								$path = $path.$tmpPath[$i]["title"];
							} 
							else if ($i == 3 || $i == $n - 3)
							{
								$path = $path.'...';
							}
						}
					}
					else
					{
						$path = "<b>Rolefolder ".$rolf[0]." not found in tree! (Role ".$loc_role["obj_id"].")</b>";
					}
					
					if ($loc_role["role_type"] != "Global")
					{
						$l_roles[$loc_role["obj_id"]] = $loc_role["title"]." ($path)";
					}
					if ($loc_role["title"] == $role["name"])
					{
						$pre_select = $loc_role;
					}
				}
		} //foreach role

		// local roles
		natsort($l_roles);
		$got_locals = false;
		foreach($roles as $role_id => $role)
		{
			if ($role["type"] == "Local")
			{
				if (! $got_locals)
				{
					$got_locals = true;

					$this->tpl->setCurrentBlock("local_role_section");
					$this->tpl->setVariable("TXT_LOCAL_ROLES_IMPORT", $this->lng->txt("roles_of_import_local"));
					$this->tpl->setVariable("TXT_LOCAL_ROLES", $this->lng->txt("assign_local_role"));
				}

				$role_select = ilUtil::formSelect($pre_select, "role_assign[".$role_id."]", $l_roles, false, true);
				$this->tpl->setCurrentBlock("local_role");
				$this->tpl->setVariable("TXT_IMPORT_LOCAL_ROLE", $role["name"]." [".$role_id."]");
				$this->tpl->setVariable("SELECT_LOCAL_ROLE", $role_select);
				$this->tpl->parseCurrentBlock();
			}
		}

		// 
 
		$this->tpl->setVariable("TXT_CONFLICT_HANDLING", $lng->txt("conflict_handling"));
		$handlers = array(
			IL_IGNORE_ON_CONFLICT => "ignore_on_conflict",
			IL_UPDATE_ON_CONFLICT => "update_on_conflict"
		);
		$this->tpl->setVariable("TXT_CONFLICT_HANDLING_INFO", str_replace('\n','<br>',$this->lng->txt("usrimport_conflict_handling_info")));
		$this->tpl->setVariable("TXT_CONFLICT_CHOICE", $lng->txt("conflict_handling"));
		$this->tpl->setVariable("SELECT_CONFLICT", ilUtil::formSelect(IL_IGNORE_ON_CONFLICT, "conflict_handling_choice", $handlers, false, false));
	}

	/**
	* import users
	*/
	function importUsersObject()
	{
		include_once './classes/class.ilObjRole.php';
		include_once './classes/class.ilUserImportParser.php';

		global $rbacreview, $rbacsystem, $tree, $lng;

		switch ($_POST["conflict_handling_choice"])
		{
			case "update_on_conflict" :
				$rule = IL_UPDATE_ON_CONFLICT;
				break;
			case "ignore_on_conflict" :
			default :
				$rule = IL_IGNORE_ON_CONFLICT;
				break;
		}
		$importParser = new ilUserImportParser($_POST["xml_file"],  IL_USER_IMPORT, $rule);
		$importParser->setFolderId($this->object->getRefId());

		// Catch hack attempts
		// We check here again, if the role folders are in the tree, and if the
		// user has write permission on the roles.
		if ($_POST["role_assign"])
		{
			$global_roles = $rbacreview->getGlobalRoles();
			foreach ($_POST["role_assign"] as $role_id)
			{
				if (in_array($role_id, $global_roles))
				{
					if ($role_id == SYSTEM_ROLE_ID 
					&& ! ilObjRole::_getAssignUsersStatus($role_id))
					{
						$this->ilias->raiseError($this->lng->txt("usrimport_with_specified_role_not_permitted"), 
							$this->ilias->error_obj->MESSAGE);
					}
				}
				else
				{
					$rolf = $rbacreview->getFoldersAssignedToRole($role_id,true);
					if ($rbacreview->isDeleted($rolf[0])
						|| ! $rbacsystem->checkAccess('write',$tree->getParentId($rolf[0])))
					{

						$this->ilias->raiseError($this->lng->txt("usrimport_with_specified_role_not_permitted"), 
							$this->ilias->error_obj->MESSAGE);
						return;
					}
				}
			}
		}

		$importParser->setRoleAssignment($_POST["role_assign"]);
		$importParser->startParsing();

		switch ($importParser->getErrorLevel())
		{
			case IL_IMPORT_SUCCESS :
				sendInfo($this->lng->txt("user_imported"), true);
				break;
			case IL_IMPORT_WARNING :
				sendInfo($this->lng->txt("user_imported_with_warnings").$importParser->getProtocolAsHTML($lng->txt("import_warning_log")), true);
				break;
			case IL_IMPORT_FAILURE :
				$this->ilias->raiseError(
					$this->lng->txt("user_import_failed")
					.$importParser->getProtocolAsHTML($lng->txt("import_failure_log")),
					$this->ilias->error_obj->MESSAGE
				);
				break;
		}

		if($this->ctrl->getTargetScript() == 'adm_object.php')
		{
			ilUtil::redirect($this->ctrl->getLinkTarget($this));
		}
		else
		{
			$this->ctrl->redirectByClass('ilobjcategorygui','listUsers');
		}
	}


	function appliedUsersObject()
	{
		global $rbacsystem,$ilias;

		unset($_SESSION['applied_users']);

		if (!$rbacsystem->checkAccess("visible,read",$this->object->getRefId()))
		{
			$this->ilias->raiseError($this->lng->txt("permission_denied"),$this->ilias->error_obj->MESSAGE);
		}
		
		if(!count($app_users =& $ilias->account->getAppliedUsers()))
		{
			sendInfo($this->lng->txt('no_users_applied'));

			return false;
		}

		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.usr_applied_users.html");
		$this->lng->loadLanguageModule('crs');
		
		$counter = 0;
		foreach($app_users as $usr_id)
		{
			$tmp_user =& ilObjectFactory::getInstanceByObjId($usr_id);

			$f_result[$counter][]	= ilUtil::formCheckbox(0,"users[]",$usr_id);
			$f_result[$counter][]   = $tmp_user->getLogin();
			$f_result[$counter][]	= $tmp_user->getFirstname();
			$f_result[$counter][]	= $tmp_user->getLastname();
			
			if($tmp_user->getTimeLimitUnlimited())
			{
				$f_result[$counter][]	= "<b>".$this->lng->txt('crs_unlimited')."</b>";
			}
			else
			{
				$limit = "<b>".$this->lng->txt('crs_from').'</b> '.strftime("%Y-%m-%d %R",$tmp_user->getTimeLimitFrom()).'<br />';
				$limit .= "<b>".$this->lng->txt('crs_to').'</b> '.strftime("%Y-%m-%d %R",$tmp_user->getTimeLimitUntil());

				$f_result[$counter][]	= $limit;
			}
			++$counter;
		}

		$this->__showAppliedUsersTable($f_result);

		return true;
	}

	function editAppliedUsersObject()
	{
		global $rbacsystem;

		if(!$rbacsystem->checkAccess("write", $this->ref_id))
		{
			$this->ilias->raiseError($this->lng->txt("msg_no_perm_write"),$this->ilias->error_obj->MESSAGE);
		}

		$this->lng->loadLanguageModule('crs');

		$_POST['users'] = $_SESSION['applied_users'] = ($_SESSION['applied_users'] ? $_SESSION['applied_users'] : $_POST['users']);

		if(!isset($_SESSION['error_post_vars']))
		{
			sendInfo($this->lng->txt('time_limit_add_time_limit_for_selected'));
		}

		if(!count($_POST["users"]))
		{
			sendInfo($this->lng->txt("time_limit_no_users_selected"));
			$this->appliedUsersObject();

			return false;
		}
		
		$counter = 0;
		foreach($_POST['users'] as $usr_id)
		{
			if($counter)
			{
				$title .= ', ';
			}
			$tmp_user =& ilObjectFactory::getInstanceByObjId($usr_id);
			$title .= $tmp_user->getLogin();
			++$counter;
		}
		if(strlen($title) > 79)
		{
			$title = substr($title,0,80);
			$title .= '...';
		}


		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.usr_edit_applied_users.html");
		$this->tpl->setVariable("FORMACTION",$this->ctrl->getFormAction($this));

		// LOAD SAVED DATA IN CASE OF ERROR
		$time_limit_unlimited = $_SESSION["error_post_vars"]["au"]["time_limit_unlimited"] ? 
			1 : 0;

		$time_limit_start = $_SESSION["error_post_vars"]["au"]["time_limit_start"] ? 
			$this->__toUnix($_SESSION["error_post_vars"]["au"]["time_limit_start"]) :
			time();
		$time_limit_end = $_SESSION["error_post_vars"]["au"]["time_limit_end"] ? 
			$this->__toUnix($_SESSION["error_post_vars"]["au"]["time_limit_end"]) :
			time();

		
		// SET TEXT VARIABLES
		$this->tpl->setVariable("ALT_IMG",$this->lng->txt("obj_usr"));
		$this->tpl->setVariable("TYPE_IMG",ilUtil::getImagePath("icon_usr_b.gif"));
		$this->tpl->setVariable("TITLE",$title);
		$this->tpl->setVariable("TXT_TIME_LIMIT",$this->lng->txt("time_limit"));
		$this->tpl->setVariable("TXT_TIME_LIMIT_START",$this->lng->txt("crs_start"));
		$this->tpl->setVariable("TXT_TIME_LIMIT_END",$this->lng->txt("crs_end"));
		$this->tpl->setVariable("CMD_SUBMIT","updateAppliedUsers");
		$this->tpl->setVariable("TXT_CANCEL",$this->lng->txt("cancel"));
		$this->tpl->setVariable("TXT_SUBMIT",$this->lng->txt("submit"));
		


		$this->tpl->setVariable("SELECT_TIME_LIMIT_START_DAY",$this->__getDateSelect("day","au[time_limit_start][day]",
																					 date("d",$time_limit_start)));
		$this->tpl->setVariable("SELECT_TIME_LIMIT_START_MONTH",$this->__getDateSelect("month","au[time_limit_start][month]",
																					   date("m",$time_limit_start)));
		$this->tpl->setVariable("SELECT_TIME_LIMIT_START_YEAR",$this->__getDateSelect("year","au[time_limit_start][year]",
																					  date("Y",$time_limit_start)));
		$this->tpl->setVariable("SELECT_TIME_LIMIT_START_HOUR",$this->__getDateSelect("hour","au[time_limit_start][hour]",
																					  date("G",$time_limit_start)));
		$this->tpl->setVariable("SELECT_TIME_LIMIT_START_MINUTE",$this->__getDateSelect("minute","au[time_limit_start][minute]",
																					  date("i",$time_limit_start)));
		$this->tpl->setVariable("SELECT_TIME_LIMIT_END_DAY",$this->__getDateSelect("day","au[time_limit_end][day]",
																				   date("d",$time_limit_end)));
		$this->tpl->setVariable("SELECT_TIME_LIMIT_END_MONTH",$this->__getDateSelect("month","au[time_limit_end][month]",
																					 date("m",$time_limit_end)));
		$this->tpl->setVariable("SELECT_TIME_LIMIT_END_YEAR",$this->__getDateSelect("year","au[time_limit_end][year]",
																					date("Y",$time_limit_end)));
		$this->tpl->setVariable("SELECT_TIME_LIMIT_END_HOUR",$this->__getDateSelect("hour","au[time_limit_end][hour]",
																					  date("G",$time_limit_end)));
		$this->tpl->setVariable("SELECT_TIME_LIMIT_END_MINUTE",$this->__getDateSelect("minute","au[time_limit_end][minute]",
																					  date("i",$time_limit_end)));
		if($this->ilias->account->getTimeLimitUnlimited())
		{
			$this->tpl->setVariable("ROWSPAN",3);
			$this->tpl->setCurrentBlock("unlimited");
			$this->tpl->setVariable("TXT_TIME_LIMIT_UNLIMITED",$this->lng->txt("crs_unlimited"));
			$this->tpl->setVariable("TIME_LIMIT_UNLIMITED",ilUtil::formCheckbox($time_limit_unlimited,"au[time_limit_unlimited]",1));
			$this->tpl->parseCurrentBlock();
		}
		else
		{
			$this->tpl->setVariable("ROWSPAN",2);
		}
	}

	function updateAppliedUsersObject()
	{
		global $rbacsystem;

		if(!$rbacsystem->checkAccess("write", $this->ref_id))
		{
			$this->ilias->raiseError($this->lng->txt("msg_no_perm_write"),$this->ilias->error_obj->MESSAGE);
		}

		$start	= $this->__toUnix($_POST['au']['time_limit_start']);
		$end	= $this->__toUnix($_POST['au']['time_limit_end']);

		if(!$_POST['au']['time_limit_unlimited'])
		{
			if($start > $end)
			{
				$_SESSION['error_post_vars'] = $_POST;
				sendInfo($this->lng->txt('time_limit_not_valid'));
				$this->editAppliedUsersObject();

				return false;
			}
		}
		#if(!$this->ilias->account->getTimeLimitUnlimited())
		#{
		#	if($start < $this->ilias->account->getTimeLimitFrom() or
		#	   $end > $this->ilias->account->getTimeLimitUntil())
		#	{
		#		$_SESSION['error_post_vars'] = $_POST;
		#		sendInfo($this->lng->txt('time_limit_not_within_owners'));
		#		$this->editAppliedUsersObject();

		#		return false;
		#	}
		#}

		foreach($_SESSION['applied_users'] as $usr_id)
		{
			$tmp_user =& ilObjectFactory::getInstanceByObjId($usr_id);

			$tmp_user->setTimeLimitUnlimited((int) $_POST['au']['time_limit_unlimited']);
			$tmp_user->setTimeLimitFrom($start);
			$tmp_user->setTimeLimitUntil($end);
			$tmp_user->setTimeLimitMessage(0);
			$tmp_user->update();

			unset($tmp_user);
		}

		unset($_SESSION['applied_users']);
		sendInfo($this->lng->txt('time_limit_users_updated'));
		$this->appliedUsersObject();
		
		return true;
	}

	function __showAppliedUsersTable($a_result_set)
	{
		$tbl =& $this->__initTableGUI();
		$tpl =& $tbl->getTemplateObject();

		// SET FORMAACTION
		$tpl->setCurrentBlock("tbl_form_header");

		$tpl->setVariable("FORMACTION",$this->ctrl->getFormAction($this));
		$tpl->parseCurrentBlock();

		$tpl->setCurrentBlock("tbl_action_btn");
		$tpl->setVariable("BTN_NAME",'editAppliedUsers');
		$tpl->setVariable("BTN_VALUE",$this->lng->txt('edit'));
		$tpl->parseCurrentBlock();

		$tpl->setCurrentBlock("tbl_action_row");
		$tpl->setVariable("COLUMN_COUNTS",5);
		$tpl->setVariable("IMG_ARROW", ilUtil::getImagePath("arrow_downright.gif"));
		$tpl->parseCurrentBlock();



		$tbl->setTitle($this->lng->txt("time_limit_applied_users"),"icon_usr_b.gif",$this->lng->txt("users"));
		$tbl->setHeaderNames(array('',
								   $this->lng->txt("login"),
								   $this->lng->txt("firstname"),
								   $this->lng->txt("lastname"),
								   $this->lng->txt("time_limits")));
		$tbl->setHeaderVars(array("",
								  "login",
								  "firstname",
								  "lastname",
								  "time_limit"),
							array("ref_id" => $this->object->getRefId(),
								  "cmd" => "appliedUsers"));
		$tbl->setColumnWidth(array("3%","19%","19%","19%","40%"));


		$this->__setTableGUIBasicData($tbl,$a_result_set);
		$tbl->render();

		$this->tpl->setVariable("APPLIED_USERS",$tbl->tpl->get());

		return true;
	}

	function &__initTableGUI()
	{
		include_once "./classes/class.ilTableGUI.php";

		return new ilTableGUI(0,false);
	}

	function __setTableGUIBasicData(&$tbl,&$result_set,$from = "")
	{
		$offset = $_GET["offset"];
		$order = $_GET["sort_by"];
		$direction = $_GET["sort_order"];

        //$tbl->enable("hits");
		$tbl->setOrderColumn($order);
		$tbl->setOrderDirection($direction);
		$tbl->setOffset($offset);
		$tbl->setLimit($_GET["limit"]);
		$tbl->setMaxCount(count($result_set));
		$tbl->setFooter("tblfooter",$this->lng->txt("previous"),$this->lng->txt("next"));
		$tbl->setData($result_set);
	}

	function __getDateSelect($a_type,$a_varname,$a_selected)
    {
        switch($a_type)
        {
            case "minute":
                for($i=0;$i<=60;$i++)
                {
                    $days[$i] = $i < 10 ? "0".$i : $i;
                }
                return ilUtil::formSelect($a_selected,$a_varname,$days,false,true);

            case "hour":
                for($i=0;$i<24;$i++)
                {
                    $days[$i] = $i < 10 ? "0".$i : $i;
                }
                return ilUtil::formSelect($a_selected,$a_varname,$days,false,true);

            case "day":
                for($i=1;$i<32;$i++)
                {
                    $days[$i] = $i < 10 ? "0".$i : $i;
                }
                return ilUtil::formSelect($a_selected,$a_varname,$days,false,true);

            case "month":
                for($i=1;$i<13;$i++)
                {
                    $month[$i] = $i < 10 ? "0".$i : $i;
                }
                return ilUtil::formSelect($a_selected,$a_varname,$month,false,true);

            case "year":
                for($i = date("Y",time());$i < date("Y",time()) + 3;++$i)
                {
                    $year[$i] = $i;
                }
                return ilUtil::formSelect($a_selected,$a_varname,$year,false,true);
        }
    }
	function __toUnix($a_time_arr)
    {
        return mktime($a_time_arr["hour"],
                      $a_time_arr["minute"],
                      $a_time_arr["second"],
                      $a_time_arr["month"],
                      $a_time_arr["day"],
                      $a_time_arr["year"]);
    }

	function hitsperpageObject()
	{
        parent::hitsperpageObject();
        $this->viewObject();
	}

/**
* Global user settings
*
* Allows to define global settings for user accounts
*/
	function settingsObject()
	{
		global $ilias;
		
		$this->getTemplateFile("settings","usr");

		$profile_fields = array(
			"gender",
			"password",
			"firstname",
			"lastname",
			"title",
			"upload",
			"institution",
			"department",
			"street",
			"city",
			"zipcode",
			"country",
			"phone_office",
			"phone_home",
			"phone_mobile",
			"fax",
			"email",
			"hobby",
			"matriculation",
			"referral_comment",
			"language",
			"skin_style"
		);
		foreach ($profile_fields as $field)
		{
			$this->tpl->setCurrentBlock("profile_settings");
			$this->tpl->setVariable("TXT_PROFILE_DATA", $this->lng->txt($field));
			$this->tpl->setVariable("PROFILE_OPTION_DISABLE", "disable_" . $field);
			$this->tpl->setVariable("PROFILE_OPTION_HIDE", "hide_" . $field);
			if ($ilias->getSetting("usr_settings_disable_" . $field) == 1)
			{
				$this->tpl->setVariable("CHECKED_DISABLE", " checked=\"checked\"");
			}
			if ($ilias->getSetting("usr_settings_hide_" . $field) == 1)
			{
				$this->tpl->setVariable("CHECKED_HIDE", " checked=\"checked\"");
			}
			$this->tpl->parseCurrentBlock();
		}
		$this->tpl->setVariable("FORMACTION", "adm_object.php?ref_id=".$_GET["ref_id"]."&cmd=gateway");
		$this->tpl->setVariable("TXT_HEADER_PROFILE", $this->lng->txt("usr_settings_header_profile"));
		$this->tpl->setVariable("TXT_EXPLANATION_PROFILE", $this->lng->txt("usr_settings_explanation_profile"));
		$this->tpl->setVariable("HEADER_PROFILE_DATA", $this->lng->txt("usr_settings_header_profile_profile"));
		$this->tpl->setVariable("HEADER_DISABLE", $this->lng->txt("disable"));
		$this->tpl->setVariable("HEADER_HIDE", $this->lng->txt("hide"));
		$this->tpl->setVariable("TXT_SAVE", $this->lng->txt("save"));
	}
	
	function saveGlobalUserSettingsObject()
	{
		global $ilias;
		
		$profile_fields = array(
			"gender",
			"password",
			"firstname",
			"lastname",
			"title",
			"upload",
			"institution",
			"department",
			"street",
			"city",
			"zipcode",
			"country",
			"phone_office",
			"phone_home",
			"phone_mobile",
			"fax",
			"email",
			"hobby",
			"matriculation",
			"referral_comment",
			"language",
			"skin_style"
		);
		foreach ($profile_fields as $field)
		{
			$ilias->deleteSetting("usr_settings_disable_" . $field);
			$ilias->deleteSetting("usr_settings_hide_" . $field);
			if ($_POST["chb"]["hide_" . $field])
			{
				$ilias->setSetting("usr_settings_hide_" . $field, "1");
			}
			if ($_POST["chb"]["disable_" . $field])
			{
				$ilias->setSetting("usr_settings_disable_" . $field, "1");
			}
		}
		sendInfo($this->lng->txt("usr_settings_saved"));
		$this->settingsObject();
	}
	
	
	/**
	*	build select form to distinguish between active and non-active users
	*/
	function __buildUserFilterSelect()
	{
		$action[-1] = $this->lng->txt('all_users');
		$action[1] = $this->lng->txt('usr_active_only');
		$action[0] = $this->lng->txt('usr_inactive_only');

		return  ilUtil::formSelect($_SESSION['user_filter'],"user_filter",$action,false,true);
	}

} // END class.ilObjUserFolderGUI
?>
