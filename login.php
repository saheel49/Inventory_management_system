<?php
require_once __DIR__.'/config/app.php';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/functions.php';
if(isset($_SESSION['user_id'])){ header('Location: '.APP_URL.'/dashboard.php'); exit; }
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $u=trim($_POST['username']??''); $p=$_POST['password']??'';
  $db=getDB();
  $st=$db->prepare("SELECT id,username,password_hash,full_name,dark_mode FROM users WHERE username=?");
  $st->bind_param('s',$u); $st->execute();
  $usr=$st->get_result()->fetch_assoc(); $st->close();
  if($usr && password_verify($p,$usr['password_hash'])){
    session_regenerate_id(true);
    $_SESSION['user_id']=$usr['id']; $_SESSION['username']=$usr['username'];
    $_SESSION['full_name']=$usr['full_name']; $_SESSION['dark_mode']=$usr['dark_mode'];
    $_SESSION['last_activity']=time();
    log_activity('login','User logged in');
    $db->close(); header('Location: '.APP_URL.'/dashboard.php'); exit;
  }
  $error='Invalid username or password.'; $db->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Login – <?php echo APP_NAME; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?php echo APP_URL; ?>/css/style.css">
</head>
<body class="login-page">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>

  <div class="login-card">
    <div class="login-logo-wrap">
      <div class="login-logo-box"><i class="fa-solid fa-boxes-stacked"></i></div>
      <div class="login-app-name"><?php echo htmlspecialchars(APP_NAME); ?></div>
      <div class="login-tagline">Enterprise Inventory Management</div>
    </div>

    <div class="login-clock" id="lClock">--:--:--</div>
    <div class="login-date"  id="lDate"></div>

    <?php if($error): ?>
    <div class="alert alert-danger" style="margin-bottom:14px;">
      <i class="fa-solid fa-circle-xmark"></i><?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="loginForm" novalidate>
      <div class="form-group">
        <label class="form-label">Username</label>
        <div class="input-wrap">
          <i class="fi fa-solid fa-user"></i>
          <input type="text" name="username" class="form-control"
                 placeholder="Enter username" required autofocus autocomplete="username"
                 value="<?php echo htmlspecialchars($_POST['username']??''); ?>">
        </div>
      </div>
      <div class="form-group" style="margin-bottom:20px;">
        <label class="form-label">Password</label>
        <div class="input-wrap">
          <i class="fi fa-solid fa-lock"></i>
          <input type="password" name="password" id="pwdInp" class="form-control has-right-icon"
                 placeholder="Enter password" required autocomplete="current-password">
          <i class="fi-right fa-solid fa-eye" id="togglePwd"></i>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg" id="loginBtn">
        <i class="fa-solid fa-right-to-bracket"></i> Sign In
      </button>
    </form>

    <div class="login-footer-links">
      <a href="<?php echo APP_URL; ?>/forgot_password.php"><i class="fa-solid fa-key" style="margin-right:4px;"></i>Forgot Password?</a>
      <span style="color:#334155;">InvenPro v3.0</span>
    </div>
  </div>

  <script>
    // Clock
    (function tick(){
      var n=new Date();
      var c=document.getElementById('lClock'); if(c) c.textContent=n.toLocaleTimeString('en-US',{hour12:false});
      var d=document.getElementById('lDate');  if(d) d.textContent=n.toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
      setTimeout(tick,1000);
    })();
    // Show/hide password
    document.getElementById('togglePwd').addEventListener('click',function(){
      var i=document.getElementById('pwdInp');
      var show=i.type==='password';
      i.type=show?'text':'password';
      this.className='fi-right fa-solid '+(show?'fa-eye-slash':'fa-eye');
    });
    // Loading state
    document.getElementById('loginForm').addEventListener('submit',function(){
      var b=document.getElementById('loginBtn');
      b.disabled=true; b.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Signing in…';
    });
    // Enter key
    document.addEventListener('keydown',function(e){
      if(e.key==='Enter'&&document.activeElement.tagName!=='BUTTON')
        document.getElementById('loginForm').requestSubmit();
    });
  </script>
</body>
</html>

