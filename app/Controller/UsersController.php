<?php
//app/Controller/UsersController.php
App::uses('AppController', 'Controller');
App::uses('CakeEmail', 'Network/Email'); //1

class UsersController extends AppController {

    public function beforeFilter() {
        parent::beforeFilter();
        //ユーザー自身による登録とログアウトと編集を許可する
        $this->Auth->allow('add', 'logout', 'edit', 'forgot_pass', 'reconf_pass');
    }

    public function login() {
        //post送信があればtrue
        if ($this->request->is('post')) {
            if ($this->Auth->login()) {
                $this->redirect($this->Auth->redirect());
            } else {
                $this->Flash->error(__('Invalid email or password, try again'));
            }
        }
    }

    public function logout() {
        $this->redirect($this->Auth->logout());
    }

    public function view($id = null) {
        $this->User->id = $id;
        if (!$this->User->exists()) {
            throw new NotFoundException(__('Invalid user'));
        }
        //Userモデルのメソッドを呼ぶ 
        //ログインユーザーの情報を取得
        $login_user = $this->Auth->user();
        //ビューに渡す
        $user = $this->User->findById($id);
        if (!$user) {
            throw new NotFoundException(__('Invalid post'));
        }
        $this->set('user', $user);
        $this->set('login_user', $login_user);
    }

    public function add() {
        //post送信があるかどうか判定
        if ($this->request->is('post')) {
            $this->User->create();
            if ($this->User->save($this->request->data)) {
                $this->Flash->success(__('The user has been saved'));
                return $this->redirect(array('controller' => 'posts', 'action' => 'index',));
            }
            $this->Flash->error(
                __('The user could not be saved. Please, try again.')
            );
        }
    }

    public function edit($id = null) {
        $this->User->id = $id;
        //ユーザーを取得
        $user = $this->User->findById($id);
        if (!$user) {
            throw new NotFoundException(__('Invalid post'));
        }
        $this->set('user', $user);

        if (!$this->User->exists()) {
            throw new NotFoundException(__('Invalid user'));
        }
        $login_user = $this->Auth->user();
        if ($user['User']['id'] !== $login_user['id']) {
            $this->Flash->error(__('You don\'t have permission.'));
            return $this->redirect(array('controller' => 'users', 'action' => 'view', $id));
        }
        if ($this->request->is('post') || $this->request->is('put')) {
            
           
            //アップロードしたファイルの名前を取得します
            $img = $this->request->data['Document']['image']['name'];
            //アップロードしたファイルの拡張子を取得
            $extension = substr($img, strrpos($img, '.'));
            
            //拡張子でアップロードするか判断
            $check_array = array('.jpg', '.png', '.jpeg');
            if (!in_array($extension, $check_array)) {
                $this->Flash->error(__('No image'));
            }
            //ランダムな値を取得
            $name = (uniqid(mt_rand(), true)) . $extension;
            //保存先のパスを保存、WWW_ROOTはwebrootを示します。
            $path = WWW_ROOT . 'img/';
            $uploadfile = $path . $name;
            //tmpフォルダ
            $tmp = $this->request->data['Document']['image']['tmp_name'];
            if (!move_uploaded_file($tmp, $uploadfile)) {
                $this->Flash->error(__('Failed to save image file.'));
            }
            //フォームデータを保存したら画像の名前を保存
            if ($this->User->save($this->request->data)) {
                $this->User->set('image_name', $name);
                $this->User->save();
                $this->Flash->success(__('The user has been saved'));
                return $this->redirect(array('controller' => 'users', 'action' => 'view', $id));
            }
            
        } else {
            $this->request->data = $this->User->findById($id);
            //$this->request->data['keyword']で取得できる
            unset($this->request->data['User']['password']);
        }
    }

    public function reconf_pass() {
        //post送信されたか判定
        if ($this->request->is('post')) {
            //get送信された値を取得hashがなければログイン画面へリダイレクト
            if (!$this->request->query['hash']) {
                $this->Flash->error(__('Incorrect access. Please try again.'));
                return $this->redirect(array('controller' => 'users', 'action' => 'login'));
            }
            $hash = $this->request->query['hash'];
            //送信されたhash_passの人の情報を取得
            $user = $this->User->findByHash_pass($hash);

            //制限時間を取得
            $limit = $user['User']['limit_time'];
            //現在時間を取得
            $now = strtotime('now');
            //30分以内かつ情報が更新できるか判定
            if ($limit > $now && $this->User->save($user)) {
                //成功すれば新しいパスワードと制限時間を過去に更新
                $this->User->set(array(
                                        'password' => $this->request->data['User']['password'],
                                        'limit_time' => strtotime('-1 hour')
                                    ));
                $this->User->save();
                $this->Flash->success(__('The password Update Complete'));
            } else {
                $this->Flash->error(__('Incorrect access. Please try again.'));
            }
            return $this->redirect(array('controller' => 'users', 'action' => 'login'));
        }
       
    }

    public function forgot_pass() {

        if ($this->request->is('post')) {
            //入力したメールアドレスを変数に代入
            $email = $this->request->data['User']['email'];
            //入力したメールアドレスがデータベースにあるか判定
            $user = $this->User->findByEmail($email);
           
            //ユーザーが存在した場合
            if ($user) {
                //ハッシュ処理の計算コストを指定
                $options = array('cost' => 10);
                //ランダムなパスワードを生成
                $pass = (uniqid(mt_rand(), true));
                //ハッシュ化方式にPASSWORD＿DEFAULTを指定し、パスワードをハッシュ化する。
                $hash = password_hash($pass, PASSWORD_DEFAULT, $options);
                //30分後の時間を取得
                $limit = strtotime('1800 second');

                //データベースに入力したメールアドレスのIDのものを指定し、そこのハッシュと制限時間を更新
                if ($this->User->save($user)) {
                    $this->User->set(array(
                        'hash_pass' => $hash,
                        'limit_time' => $limit
                    ));
                    $this->User->save();
                
                    //メールを送信
                    $Email = new CakeEmail();
                    $Email->to('phobia4242@gmail.com');
                    $Email->emailFormat('text');
                    
                    $Email->subject('パスワード更新メール');
                    $messages = $Email->send("下記のURLをクリックしてパスワードを再設定して下さい。\nパスワード再発行URL↓\nhttps://procir-study.site/tada/myblog/users/reconf_pass?hash=" . $hash . "");
                    $this->set('messages', $messages);

                }
            }
            //メールアドレスが存在してもしなくても表示
            $this->Flash->success(__('Email Sent.'));

        }            

    }
    
    
}

?>
