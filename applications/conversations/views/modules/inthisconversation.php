<?php if (!defined('APPLICATION')) exit(); ?>
<div class="Box InThisConversation">
   <h4><?php echo T('In this Conversation'); ?></h4>
   <ul class="PanelInfo">
   <?php foreach ($this->Data->Result() as $User): ?>
      <li>
         <?php
         $Username = htmlspecialchars(GetValue('Name', $User));
         $Photo = GetValue('Photo', $User);

         if (GetValue('Deleted', $User)) {
            echo Anchor(
               Wrap(
                  Img($Photo, array('class' => 'ProfilePhoto ProfilePhotoSmall')).' '.
                  Wrap($Username, 'del', array('class' => 'Username')),
                  'span', array('class' => 'Conversation-User',)
               ),
               UserUrl($User),
               array('title' => sprintf(T('%s has left this conversation.'), $Username))
            );
         } else {
            echo Anchor(
               Wrap(
                  Img($Photo, array('class' => 'ProfilePhoto ProfilePhotoSmall')).' '.
                  Wrap($Username, 'span', array('class' => 'Username')),
                  'span', array('class' => 'Conversation-User')
               ),
               UserUrl($User)
            );
         }
         ?>
      </li>
   <?php endforeach; ?>
   </ul>
</div>
