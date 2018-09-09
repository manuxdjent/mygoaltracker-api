<?php

/*
|--------------------------------------------------------------------------
| Api Rest para MyGoalTracker por Manuel Fernández de Ginzo
|--------------------------------------------------------------------------
|
*/

use Slim\Http\Request;
use Slim\Http\Response;

function keyCheck($hash, $public){
  $con=mysqli_connect(HOST,USER,PW,"mygoaltracker");
  if (mysqli_connect_errno()){
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
    }
    $query = "SELECT publickey, privatekey, password FROM usuarios WHERE publickey = '".$public."'";

    $result = mysqli_query($con,$query);
    if (mysqli_num_rows($result) > 0) {
      $row = $result->fetch_assoc();
      $privatekey = $row['privatekey'];
      $pw= $row['password'];
      if ($hash == hash('sha256', $privatekey.$pw)) {
        return true;
      } else return false;

    } else return false;
  mysqli_close($con);
}
$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
            ->withHeader('Access-Control-Allow-Origin', '*:8100')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});
$app->post('/login', function(Request $request, Response $response, array $args){
    try{
      $allPostPutVars = $request->getParsedBody();

      $email = $allPostPutVars['email'];
      $pw = $allPostPutVars['pw'];

      $db = $this->get("db");
      $con=mysqli_connect($db[0],$db[1],$db[2],$db[3]);
      if (mysqli_connect_errno()){
        echo '<script>console.log("Failed to connect to MySQL: " '. mysqli_connect_error().');</script>';
        }
      else{
        $query = "SELECT privatekey, publickey, id FROM usuarios WHERE email = '".$email."' AND password = '".$pw."'";
        $result = mysqli_query($con,$query);

        if (mysqli_num_rows($result) > 0){
          $row = $result->fetch_assoc();
          $privatekey = $row['privatekey'];
          $publickey = $row['publickey'];
          $userID = $row['id'];

            $hash = hash('sha256', $privatekey.$pw);
            $res = array('hash' => $hash, 'pb' => $publickey, 'userID' => $userID);

            $response->withHeader('Content-Type', 'application/json');
            $response->write(json_encode($res));
            return $response;
        } else{
          $res = array('error' => 'LF');
          $response->write(json_encode($res));
          return $response;
        }
        mysqli_close($con);
      }
  }
  catch (Exception $e){
      $this->logger->error($e->getMessage());
  }
});

$app->post('/signup', function(Request $request, Response $response, array $args){
    try{
      $allPostPutVars = $request->getParsedBody();

      $email = $allPostPutVars['email'];
      $pw = $allPostPutVars['pw'];
      $meta = $allPostPutVars['meta'];

      $db = $this->get("db");
      $con=mysqli_connect($db[0],$db[1],$db[2],$db[3]);
      if (mysqli_connect_errno()){
        echo '<script>console.log("Failed to connect to MySQL: " '. mysqli_connect_error().');</script>';
        }
      else{
        $query = "SELECT email FROM usuarios WHERE email = '".$email."'";
        $result = mysqli_query($con,$query);
        if (mysqli_num_rows($result) > 0){
          $msg = array('result' => false, 'message' => 'Email ya registrado.');
          $response->write(json_encode($msg));
          return $response;
        } else {
          $publickey = hash('sha256', time());
          sleep(1);
          $privatekey = hash('sha256', time());

          $query = "INSERT INTO usuarios(id,password,publickey,privatekey,email,meta)
          VALUES (NULL,'$pw','$publickey','$privatekey','$email','$meta')";

          try {
            $result = mysqli_query($con,$query);
            if ($result){
              $msg = array('result' => true, 'message' => 'OK', 'query' => $query , 'dale' => $result);
              $response->write(json_encode($msg));
              return $response;
            } else {
              $msg = array('result' => false, 'message' => $con->error);
              $response->write(json_encode($msg));
              return $response;
            }

          } catch (Exception $e) {
            $this->logger->error($e->getMessage());
          }
        }
        mysqli_close($con);
      }
  }
  catch (Exception $e){
      $this->logger->error($e->getMessage());
  }

});

$app->get('/alimentos', function (Request $request, Response $response, array $args) {
  $hash = $request->getHeader('hash');
  $pb = $request->getHeader('pb');
  $userID = $request->getHeader('userID');
  $input = $request->getHeader('input')[0];

  if (keyCheck($hash[0],$pb[0])) {
    $db = $this->get("db");
    $con=mysqli_connect($db[0],$db[1],$db[2],$db[3]);

    if (mysqli_connect_errno()){
      echo "Failed to connect to MySQL: " . mysqli_connect_error();
      }

    $query = "select * from alimento where nombre LIKE '%".$input."%'";
    $result = mysqli_query($con,$query);

    if ($result->num_rows === 0) {
      $msg = array('result' => false);
      $response->write(json_encode($msg));
      return $response;
    }
    else {
      while ($row = $result->fetch_assoc()){
        $data[] = $row;
      }

      mysqli_close($con);
      echo json_encode($data);
    }
  } else{
    $newResponse = $response->withStatus(403);
    return $newResponse;
  }
});



$app->get('/peso', function (Request $request, Response $response, array $args) {
  if ($request->hasHeader('hash')) {
    $hash = $request->getHeader('hash');
    $pb = $request->getHeader('pb');
    $userID = $request->getHeader('userID');
      if (keyCheck($hash[0],$pb[0])) {
        $db = $this->get("db");
        $con=mysqli_connect($db[0],$db[1],$db[2],$db[3]);

        if (mysqli_connect_errno()){
          echo "Failed to connect to MySQL: " . mysqli_connect_error();
          }

        $query = "select peso,fecha,meta from pesoUsuario inner join usuarios on usuarios.id = pesoUsuario.usuario_id
                 where usuario_id = ".$userID[0]." order by 2 desc";

        $result = mysqli_query($con,$query);

        if ($result->num_rows === 0) {
          $msg = array('result' => false);
          $response->write(json_encode($msg));
          return $response;
        }
        else {
          while ($row = $result->fetch_assoc()){
            $data[] = $row;
          }
          mysqli_close($con);
          echo json_encode($data);
        }
      } else{
        $msg = array('result' => 'keycheck fail');
        $response->write(json_encode($msg));
        return $response;
      }
  }else{
    $msg = array('result' => 'no headers');
    $response->write(json_encode($msg));
    return $response;
  }
});
$app->post('/pesoMeta', function (Request $request, Response $response, array $args) {
  if ($request->hasHeader('hash')) {

    $allPostPutVars = $request->getParsedBody();
    $peso = $allPostPutVars['peso'];

    $hash = $request->getHeader('hash');
    $pb = $request->getHeader('pb');
    $userID = $request->getHeader('userID');

      if (keyCheck($hash[0],$pb[0])) {
        $db = $this->get("db");
        $con=mysqli_connect($db[0],$db[1],$db[2],$db[3]);

        if (mysqli_connect_errno()){
          echo "Failed to connect to MySQL: " . mysqli_connect_error();
          }

        $query = "update usuarios set meta = ".$peso." where id = ".$userID[0];

        $result = mysqli_query($con,$query);
        try {
          if ($result){
            $msg = array('result' => true, 'message' => 'Peso added.');
            $response->write(json_encode($msg));
            return $response;
          } else {
            $msg = array('result' => false, 'message' => $con->error, 'sql' => $query);
            $response->write(json_encode($msg));
            return $response;
          }

        } catch (Exception $e) {
          $this->logger->error($e->getMessage());
        }
  }else{
    $msg = array('result' => 'no headers');
    $response->write(json_encode($msg));
    return $response;
  }
}
});
$app->post('/peso', function (Request $request, Response $response, array $args) {
  if ($request->hasHeader('hash')) {

    $allPostPutVars = $request->getParsedBody();
    $peso = $allPostPutVars['peso'];

    $hash = $request->getHeader('hash');
    $pb = $request->getHeader('pb');
    $userID = $request->getHeader('userID');

      if (keyCheck($hash[0],$pb[0])) {
        $db = $this->get("db");
        $con=mysqli_connect($db[0],$db[1],$db[2],$db[3]);

        if (mysqli_connect_errno()){
          echo "Failed to connect to MySQL: " . mysqli_connect_error();
          }

        $query = "insert into pesoUsuario(id,usuario_id,peso) values (NULL,".$userID[0].",".$peso.")";

        $result = mysqli_query($con,$query);
        try {
          if ($result){
            $msg = array('result' => true, 'message' => 'Peso added.');
            $response->write(json_encode($msg));
            return $response;
          } else {
            $msg = array('result' => false, 'message' => $con->error, 'sql' => $query);
            $response->write(json_encode($msg));
            return $response;
          }

        } catch (Exception $e) {
          $this->logger->error($e->getMessage());
        }
  }else{
    $msg = array('result' => 'no headers');
    $response->write(json_encode($msg));
    return $response;
  }
}
});

$app->get('/registro', function (Request $request, Response $response, array $args) {
  $hash = $request->getHeader('hash');
  $pb = $request->getHeader('pb');
  $userID = $request->getHeader('userID');
  $fechaDispositivo = $request->getHeader('fechaDispositivo');
  $pag = $request->getHeader('pag');

  if (keyCheck($hash[0],$pb[0])) {
    $db = $this->get("db");
    $con=mysqli_connect($db[0],$db[1],$db[2],$db[3]);

    if (mysqli_connect_errno()){
      echo "Failed to connect to MySQL: " . mysqli_connect_error();
      }
      if ($pag[0] == 'landing') $where = " and registro.fecha = '".$fechaDispositivo[0]."' ";
      else $where = " ";

    $query = "select * from registro
                inner join usuarios_has_registro on registro.id = usuarios_has_registro.registro_id
                inner join registro_has_alimento on registro_has_alimento.registro_id = usuarios_has_registro.registro_id
                inner join alimento on registro_has_alimento.alimento_id = alimento.id
              where usuarios_has_registro.usuarios_id = ".$userID[0].$where;;

    $result = mysqli_query($con,$query);

    if ($result->num_rows === 0) {
      $msg = array('result' => false);
      $response->write(json_encode($msg));
      return $response;
    }
    else {
      while ($row = $result->fetch_assoc()){
        $data[] = $row;
      }
      mysqli_close($con);
      echo json_encode($data);
    }
  } else{
    $newResponse = $response->withStatus(403);
    return $newResponse;
  }
});

$app->post('/registro', function (Request $request, Response $response, array $args) {
  if ($request->hasHeader('registroID')) {

    $registroID = $request->getHeader('registroID');
    $hash = $request->getHeader('hash');
    $pb = $request->getHeader('pb');

    if (keyCheck($hash[0],$pb[0])) {
      $db = $this->get("db");
      $con=mysqli_connect($db[0],$db[1],$db[2],$db[3]);

      if (mysqli_connect_errno()){
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
        }

        $query = "delete from registro where id = ".$registroID[0];
        $result = mysqli_query($con,$query);

        if ($result) {
          $msg = array('result' => 'Registro borrado.');
          $response->write(json_encode($msg));
          mysqli_close($con);
        }

      }
  }
  else{
  $hash = $request->getHeader('hash');
  $pb = $request->getHeader('pb');
  $userID = $request->getHeader('userID');
  $fechaDispositivo = $request->getHeader('fechaDispositivo');
  $caloriasAlimento = $request->getHeader('caloriasAlimento');
  $alimentoID = $request->getHeader('alimentoID');
  $tipo = $request->getHeader('tipo');

  $allPostPutVars = $request->getParsedBody();
  $cantidad = $allPostPutVars['cantidad'];

  if (keyCheck($hash[0],$pb[0])) {
    $db = $this->get("db");
    $con=mysqli_connect($db[0],$db[1],$db[2],$db[3]);

    if (mysqli_connect_errno()){
      echo "Failed to connect to MySQL: " . mysqli_connect_error();
      }
    $kcalTotales = ($cantidad * $caloriasAlimento[0]) / 100;

    $query = "insert into registro(id,fecha,tipo,kc) values(NULL, '".$fechaDispositivo[0]."','".$tipo[0]."',".$kcalTotales.")";

    $result = mysqli_query($con,$query);

    if ($result) {
      $msg = array('result' => 'Registro añadido.');
      $response->write(json_encode($msg));
    }
    else {
      $msg = array('result' => false, 'message' => $con->error);
      $response->write(json_encode($msg));
      mysqli_close($con);
      return $response;
    }

    $query = "select id from registro order by 1 desc limit 1";
    $result = mysqli_query($con,$query);

    if ($result->num_rows === 0) {
      $response->write(json_encode($msg));
    }
    else {
      while ($row = $result->fetch_assoc()){
        $idRegistro[] = $row;
      }
    }

    $query = "insert into usuarios_has_registro(usuarios_id,registro_id) values(".$userID[0].",".$idRegistro[0]['id'].")";

    $result = mysqli_query($con,$query);

    if ($result) {
      $msg = array('result' => 'Usuarios has registro añadido.');
      $response->write(json_encode($msg));
      return $response;
    }
    else {
      $msg = array('result' => false);
      $response->write(json_encode($msg));
      mysqli_close($con);
      return $response;
    }
    $query = "insert into registro_has_alimento(registro_id,alimento_id,cantidad) values(".$idRegistro[0]['id'].",".$alimentoID[0].",".$cantidad.")";

    $result = mysqli_query($con,$query);

    if ($result) {
      $msg = array('result' => 'Registro has alimento añadido.');
      $response->write(json_encode($msg));
      return $response;
    }
    else {
      $msg = array('result' => false);
      $response->write(json_encode($msg));
      mysqli_close($con);
      return $response;
    }
  } else{
    mysqli_close($con);
    $newResponse = $response->withStatus(403);
    return $newResponse;
  }
}
});
