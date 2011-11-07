<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

$PluginInfo['ProfileExtender'] = array(
   'Name' => 'Profile Extender',
   'Description' => 'Add fields (like status, location, or gamer tags) to profiles and registration.',
   'Version' => '2.0',
   'RequiredApplications' => array('Vanilla' => '2.1a1'),
   'MobileFriendly' => TRUE,
   'RegisterPermissions' => array('Plugins.ProfileExtender.Add'),
   'SettingsUrl' => '/dashboard/settings/profileextender',
   'SettingsPermission' => 'Garden.Settings.Manage',
   'Author' => "Matt Lincoln Russell",
   'AuthorEmail' => 'lincoln@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com'
);

/**
 * Plugin to add additional fields to user profiles.
 *
 * Based on Mark O'Sullivan's (mark@vanillaforums.com) CustomProfileFields plugin.
 * When enabled, this plugin will import content from CustomProfileFields.
 */
class ProfileExtenderPlugin extends Gdn_Plugin {
   /**
    * Add the Dashboard menu item.
    */
   public function Base_GetAppSettingsMenuItems_Handler($Sender) {
      $Menu = &$Sender->EventArguments['SideMenu'];
      $Menu->AddLink('Users', T('Profile Fields'), 'settings/profileextender', 'Garden.Settings.Manage');
   }
   
   /**
    * Add fields to registration forms.
    */
   public function EntryController_RegisterBeforePassword_Handler($Sender) {
      $Sender->RegistrationFields = C('Plugins.ProfileExtender.RegistrationFields', '');
      $Sender->RegistrationFields = explode(',', $Sender->RegistrationFields);               
      $Sender->Render($this->GetView('registrationfields.php'));
   }
   
   /**
    * Add fields to edit profile form.
    */
   public function ProfileController_EditMyAccountAfter_Handler($Sender) {
      $this->ProfileFields($Sender);
   }
   
   /**
    * Display custom profile fields.
    *
    * @access private
    */
   private function ProfileFields($Sender) {
      // Retrieve user's existing profile fields
      $Sender->ProfileFields = explode(',', C('Plugins.ProfileExtender.ProfileFields', ''));
      $Sender->IsPostBack = $Sender->Form->IsPostBack();
      
      $Sender->UserFields = array();
      if (is_object($Sender->User))
         $Sender->UserFields = Gdn::UserModel()->GetMeta($Sender->User->UserID, 'Profile_%', 'Profile_');
               
      $Sender->Render($this->GetView('profilefields.php'));
   }
   
   /**
    * Settings page.
    */
   public function SettingsController_ProfileExtender_Create($Sender) {
      $Conf = new ConfigurationModule($Sender);
      $Conf->Initialize(array(
         'Plugins.ProfileExtender.ProfileFields' => array('Control' => 'TextBox', 'Options' => array('MultiLine' => TRUE)),
         'Plugins.ProfileExtender.RegistrationFields' => array('Control' => 'TextBox', 'Options' => array('MultiLine' => TRUE)),
         'Plugins.ProfileExtender.HideFields' => array('Control' => 'TextBox', 'Options' => array('MultiLine' => TRUE)),
         'Plugins.ProfileExtender.TextMaxLength' => array('Control' => 'TextBox'),
      ));

      $Sender->AddSideMenu('settings/profileextender');
      $Sender->SetData('Title', T('Profile Fields'));
      $Sender->ConfigurationModule = $Conf;
      $Conf->RenderAll();
   }
   
   /**
    * Trim values in array to specified length.
    *
    * @access private
    */
   private function TrimValues(&$Array, $Length = 140) {
      foreach ($Array as $Key => $Val) {
         $Array[$Key] = substr($Val, 0, $Length);
      }
   }
   
   /**
    * Display custom fields on Edit User form.
    */
   public function UserController_AfterFormInputs_Handler($Sender) {
      echo '<ul>';
      $this->ProfileFields($Sender);
      echo '</ul>';
   }
   
   /**
    * Display custom fields on Profile.
    */
   public function UserInfoModule_OnBasicInfo_Handler($Sender) {
      
      try {
         // Render the custom fields
         $Fields = Gdn::UserModel()->GetMeta($Sender->User->UserID, 'Profile_%', 'Profile_');
         
         // Order, Part 1: Get user's fields by order of Plugins.ProfileExtender.ProfileFields
         $Listed = (array)explode(',', C('Plugins.ProfileExtender.ProfileFields'));
         $Fields1 = array();
         foreach ($Listed as $FieldName) {
            $Fields1[$FieldName] = $Fields[$FieldName];
         }
         
         // Order, Part 2: Append the user's arbitrary custom fields (if they have any) alphabetically by label
         $Fields2 = array_diff_key($Fields, $Listed);
         ksort($Fields2);
         $Fields = array_merge($Fields1, $Fields2);
         
         // Import from CustomProfileFields if available
         if (!count($Fields) && is_object($Sender->User) && C('Plugins.CustomProfileFields.SuggestedFields', FALSE)) {
			   $Fields = Gdn::UserModel()->GetAttribute($Sender->User->UserID, 'CustomProfileFields', FALSE);
			   if ($Fields) {
			      // Migrate to UserMeta & delete original
			      Gdn::UserModel()->SetMeta($Sender->User->UserID, $Fields, 'Profile_');
			      Gdn::UserModel()->SaveAttribute($Sender->User->UserID, 'CustomProfileFields', FALSE);
			   }
         }
         
         // Display all non-hidden fields
         $HideFields = (array)explode(',', C('Plugins.ProfileExtender.HideFields'));
         foreach ($Fields as $Label => $Value) {
            if (in_array($Label, $HideFields))
               continue;
            echo '<dt class="ProfileExtend Profile'.Gdn_Format::AlphaNumeric($Label).'">'.Gdn_Format::Text($Label).'</dt>';
            echo '<dd class="ProfileExtend Profile'.Gdn_Format::AlphaNumeric($Label).'">'.Gdn_Format::Links(htmlspecialchars($Value)).'</dd>';
         }
      } catch (Exception $ex) {
         // No errors
      }
   }
   
   /**
    * Save custom profile fields when saving the user.
    */
   public function UserModel_AfterSave_Handler($Sender) {
      $ValueLimit = Gdn::Session()->CheckPermission('Garden.Moderation.Manage') ? 255 : C('Plugins.ProfileExtender.TextMaxLength', 140);
      $UserID = GetValue('UserID', $Sender->EventArguments);
      $FormPostValues = GetValue('FormPostValues', $Sender->EventArguments);

      // Build array of all extended profile fields
      $Fields = FALSE;
      if (is_array($FormPostValues)) {
         $CustomLabels = GetValue('CustomLabel', $FormPostValues);
         $CustomValues = GetValue('CustomValue', $FormPostValues);
         if (is_array($CustomLabels) && is_array($CustomValues)) {
            $this->TrimValues($CustomLabels, 50);
            $this->TrimValues($CustomValues, $ValueLimit);
            $Fields = array_combine($CustomLabels, $CustomValues);
         }
      }
      
      // Delete any custom fields that had their label removed
      $CurrentFields = Gdn::UserModel()->GetMeta($UserID, 'Profile_%', 'Profile_');
      foreach ($CurrentFields as $CurrentKey => $CurrentValue) {
         if (!array_key_exists($CurrentKey, $Fields))
            $Fields[$CurrentKey] = NULL;
      }
      
      // Delete any custom fields that had their value removed
      foreach ($Fields as $FieldKey => $FieldValue) {
         if ($FieldValue == '')
            $Fields[$FieldKey] = NULL; 
      }
      
      // Update UserMeta
      if ($UserID > 0 && is_array($Fields)) {
         $UserModel = new UserModel();
         $UserModel->SetMeta($UserID, $Fields, 'Profile_');
      }
   }
   
   /**
	 * Add fields during registration.
	 */
	public function UserModel_BeforeInsertUser_Handler($Sender) {	
      $Fields = Gdn::Controller()->Form->FormValues();
      
	}
   
   /**
    * Add suggested fields on install & convert CustomProfileField settings.
    */
   public function Setup() {
      // Import CustomProfileFields settings
      if ($Suggested = C('Plugins.CustomProfileFields.SuggestedFields', FALSE))
         SaveToConfig('Plugins.ProfileExtender.ProfileFields', $Suggested);
      if ($Hidden = C('Plugins.CustomProfileFields.HideFields', FALSE))
         SaveToConfig('Plugins.ProfileExtender.HideFields', $Hidden);
      if ($Length = C('Plugins.CustomProfileFields.ValueLength', FALSE))
         SaveToConfig('Plugins.ProfileExtender.TextMaxLength', $Length);
            
      // Set defaults
      if (!C('Plugins.ProfileExtender.ProfileFields', FALSE))
         SaveToConfig('Plugins.ProfileExtender.ProfileFields', 'Location,Facebook,Twitter,Website');
      if (!C('Plugins.ProfileExtender.RegistrationFields', FALSE))
         SaveToConfig('Plugins.ProfileExtender.RegistrationFields', 'Location');
      if (!C('Plugins.ProfileExtender.TextMaxLength', FALSE))
         SaveToConfig('Plugins.ProfileExtender.TextMaxLength', 140);
   }
}