<!DOCTYPE html>
<html>
<head>
  <title><?=$title?></title>
  <link rel="stylesheet" href="/style/screen.css" />
  <?php if (isset($stylesheets)): ?>
    <?php foreach ($stylesheets as $stylesheet): ?>
      <link rel="stylesheet" href="<?=$stylesheet?>" />
    <?php endforeach; ?>
  <?php endif; ?>
  <?php if (isset($scripts)): ?>
    <?php foreach ($scripts as $script): ?>
      <script src="<?=$script?>"></script>
    <?php endforeach; ?>
  <?php endif; ?>
</head>
<?php // Setup some common PHP related stuff, and our menu.
  $nano = \Nano3\get_instance();
  $menu = array(
    PAGE_DEFAULT          => array('name'=>"Home",     'root'=>True),
    PAGE_LOGIN            => array('name'=>"Login",    'user'=>False),
    PAGE_LOGOUT           => array('name'=>"Logout"    'user'=>True),
  );
?>
<body>
  <div id="layout">
  <div id="topmenu">
<?php 
foreach ($menu as $path => $item):
  if (isset($item['user']))
  {
    if ($item['user'] && !isset($user)) 
      continue;
    elseif (!$item['user'] && isset($user)) 
      continue;
  }
  // Add extra flags here.

  if (isset($item['root']) && $item['root'])
  { // The default URL, i.e. no extra paths.
    if (isset($_SERVER['PATH_INFO']))
      $my_path = $_SERVER['PATH_INFO'];
    else
      $my_path = $_SERVER['REQUEST_URI'];
    $is_current = ($my_path == $path);
  }
  else
  { // A page, in a certain namespace.
    $pathsplit = explode('/', $path);
    $prefix = $pathsplit[1];
    $is_current = strpos($_SERVER['REQUEST_URI'], $prefix) !== False;
  }
?>
<a 
<?php if ($is_current): ?>
 class="current"
<?php endif; ?>
 href="<?=$path?>"><?=$item['name']?></a>
<?php endforeach; ?>
    &nbsp;&nbsp;&mdash;&nbsp;&nbsp;
    <?=$user->name?>
    </div>
    <h1><?=$title?></h1>
    <div id="screen">
      <?=$view_content?>
    </div>
    <div id="copyright">
      Copright &copy; 2012, 
      <a href="http://luminaryn.com/">Luminaryn Enterprises</a>.
    </div>
  </div>
</body>
</html>