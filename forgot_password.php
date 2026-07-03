<?php
require_once __DIR__.'/config/app.php';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/functions.php';
$ok=''; $err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $u=trim($_POST['username']??''); $pv=$_POST['prev_password']??'';
  $np=$_POST['new_password']??''; $cp=$_POST['confirm_password']??'';
  if(!$u||!$pv||!$np||!$cp) $err='All fields are required.';
  elseif($np!==$cp) $err='New passwords do not match.';
  elseif(strlen($np)<6) $err='Password must be at least 6 characters.';
  else{
    $db=getDB();
    $st=$db->prepare("SELECT id,password_hash FROM users WHERE username=?");
    $st->bind_param('s',$u); $st->execute();
    $usr=$st->get_result()->fetch_assoc(); $st->close();
    if(!$usr){ $err='Username not found.'; $db->close(); }
    else{
      $match=password_verify($pv,$usr['password_hash']);
      if(!$match){
        $h=$db->prepare("SELECT password_hash FROM password_history WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
        $h->bind_param('i',$usr['id']); $h->execute(); $hr=$h->get_result();
        while($row=$hr->fetch_assoc()){ if(password_verify($pv,$row['password_hash'])){ $match=true; break; } }
        $h->close();
      }
      if(!$match){ $err='Previous password does not match.'; $db->close(); }
      else{
        $hash=password_hash($np,PASSWORD_DEFAULT);
        $uu=$db->prepare("UPDATE users SET password_hash=? WHERE id=?");
        $uu->bind_param('si',$hash,$usr['id']); $uu->execute(); $uu->close();
        $pp=$db->prepare("INSERT INTO password_history (user_id,password_hash) VALUES (?,?)");
        $pp->bind_param('is',$usr['id'],$hash); $pp->execute(); $pp->close();
        log_activity('password_change','Password changed for: '.$u);
        $ok='Password changed! Redirecting…'; $db->close();
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Reset Password – <?php echo APP_NAME; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?php echo APP_URL; ?>/css/style.css">
</head>
<body class="forgot-page">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>

  <div class="forgot-card">
    <div class="login-logo-wrap">
      <div class="login-logo-box"><i class="fa-solid fa-key"></i></div>
      <div class="login-app-name">Reset Password</div>
      <div class="login-tagline">Enter your credentials to set a new password</div>
    </div>

    <?php if($err): ?>
    <div class="alert alert-danger" style="margin-bottom:14px;">
      <i class="fa-solid fa-circle-xmark"></i><?php echo htmlspecialchars($err); ?>
    </div>
    <?php endif; ?>
    <?php if($ok): ?>
    <div class="alert alert-success" style="margin-bottom:14px;">
      <i class="fa-solid fa-circle-check"></i><?php echo htmlspecialchars($ok); ?>
    </div>
    <script>setTimeout(()=>location.href='<?php echo APP_URL; ?>/login.php',2500);</script>
    <?php else: ?>

    <form method="POST" novalidate>
      <div class="form-group">
        <label class="form-label">Username</label>
        <div class="input-wrap"><i class="fi fa-solid fa-user"></i>
        <input type="text" name="username" class="form-control" placeholder="Your username" required autofocus></div>
      </div>
      <div class="form-group">
        <label class="form-label">Any Previous Password</label>
        <div class="input-wrap"><i class="fi fa-solid fa-lock"></i>
        <input type="password" name="prev_password" class="form-control" placeholder="A previous password" required></div>
      </div>
      <div class="form-group">
        <label class="form-label">New Password</label>
        <div class="input-wrap"><i class="fi fa-solid fa-lock"></i>
        <input type="password" name="new_password" class="form-control" placeholder="Min 6 characters" required></div>
      </div>
      <div class="form-group" style="margin-bottom:20px;">
        <label class="form-label">Confirm New Password</label>
        <div class="input-wrap"><i class="fi fa-solid fa-lock"></i>
        <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required></div>
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg">
        <i class="fa-solid fa-floppy-disk"></i> Reset Password
      </button>
    </form>
    <?php endif; ?>

    <div class="login-footer-links" style="margin-top:16px;">
      <a href="<?php echo APP_URL; ?>/login.php"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
    </div>
  </div>
</body>
</html>

