<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

/**
 * Update Controller
 */
class DownloadController extends UpdateController {
   
   public function Initialize() {
      parent::Initialize();
      
      $this->DownloadModel = new DownloadModel();
      /*

      $DefaultOptions = array(
         'SendCookies'     => TRUE,
         'RequestMethod'   => 'GET',
         'FollowRedirects' => TRUE,
         'SaveFile'        => FALSE,
         'Timeout'         => C('Garden.SocketTimeout', 2.0),
         'BufferSize'      => 8192,
         'UserAgent'       => GetValue('HTTP_USER_AGENT', $_SERVER, 'Vanilla/2.0'),
         'Referer'         => Gdn_Url::WebRoot(TRUE),
         'Authentication'  => FALSE,
         'Username'        => NULL,
         'Password'        => NULL
      );
*/
      
      $Results = $this->DownloadModel->Request(
         "http://www.vanillaforums.org/uploads/addons/LABOJ70HFYO0.zip",
         NULL,
         array(
            'SaveFile'     => '/www/vanilla/vanilla/cache',
            'SendCookies'  => FALSE
         )
      );
      
      var_dump($Results);
      die();
   }

   public function Index() {
   
      $this->Render();
   }
   
   public function Get() {
      $this->GetBackupTask();
      $RenderController = 'download';
      
      $RequestType = $this->RequestType();
      switch ($RequestType) {
         case 'ui':
            $this->DownloadTitle = T('Downloading updates...');
            $this->DownloadGetTasks = array(
               'update/download/get'   => $this->DownloadTitle
            );
            $RenderView = 'get';
            break;
            
         case 'check':
         case 'perform':

            $RenderView = 'blank';
            $RenderController = 'update';

            if ($RequestType == 'perform') {
               // Don't interrupt if another process is already doing this.
               if ($this->Update->Progress('download','get')) exit();
               $this->DownloadModel->BackupFiles($this->GetBackupTask(), $this->Update);
            }
            
            if ($RequestType == 'check') {
               $ThisAction = $this->Update->GetTask('backup','files');
               $this->SetData('Completion', GetValue('Completion',$ThisAction,NULL));
               $this->SetData('Message', $this->Update->GetMeta('backup/message'));
               $this->Update->SetMeta('backup/message');
               $this->SetData('Menu', $this->UpdateModule->ToString());
            }
            
            break;
      }
      $this->Render($RenderView,$RenderController);
   }
   
}