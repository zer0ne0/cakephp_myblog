<!-- app/View/Users/add.ctp -->
<div class='users form'>
<?php echo $this->Flash->render('auth'); ?>
<?php echo $this->Form->create('User'); ?>
    <fieldset>
        <legend><?php echo __('Add User'); ?></legend>
        <?php 
        echo $this->Form->input('username');
        echo $this->Form->input('email');
        echo $this->Form->input('password');
        ?>
    </fieldset>
 <?php echo $this->Form->end(__('Submit')); ?>
 </div>
 <?php echo $this->Html->link('Login', array('controller' => 'users', 'action' => 'login')); ?> <br>
 <?php echo $this->Html->link('Top', array('controller' => 'posts', 'action' => 'index')); ?> 
