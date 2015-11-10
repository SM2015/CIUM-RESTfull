<?php
namespace App\Http\Controllers\v1\Sistema;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB; 
use Sentry;
use App\Models\Sistema\usuario;
/**
* Controlador Usuario
* 
* @package    CIUM API
* @subpackage Controlador
* @author     Eliecer Ramirez Esquinca <ramirez.esquinca@gmail.com>
* @created    2015-07-20

* Controlador `Usuario`: Manejo de usuarios del sistema
*
*/
class UsuarioController extends Controller 
{
	/**
	 * Muestra una lista de los recurso según los parametros a procesar en la petición.
	 *
	 * <h3>Lista de parametros Request:</h3>
	 * <Ul>Paginación
	 * <Li> <code>$pagina</code> numero del puntero(offset) para la sentencia limit </ li>
	 * <Li> <code>$limite</code> numero de filas a mostrar por página</ li>	 
	 * </Ul>
	 * <Ul>Busqueda
	 * <Li> <code>$valor</code> string con el valor para hacer la busqueda</ li>
	 * <Li> <code>$order</code> campo de la base de datos por la que se debe ordenar la información. Por Defaul es ASC, pero si se antepone el signo - es de manera DESC</ li>	 
	 * </Ul>
	 *
	 * Ejemplo ordenamiento con respecto a id:
	 * <code>
	 * http://url?pagina=1&limite=5&order=id ASC 
	 * </code>
	 * <code>
	 * http://url?pagina=1&limite=5&order=-id DESC
	 * </code>
	 *
	 * Todo Los parametros son opcionales, pero si existe pagina debe de existir tambien limite
	 * @return Response 
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function index()
	{
		$datos = Request::all();
		
		// Si existe el paarametro pagina en la url devolver las filas según sea el caso
		// si no existe parametros en la url devolver todos las filas de la tabla correspondiente
		// esta opción es para devolver todos los datos cuando la tabla es de tipo catálogo
		if(array_key_exists('pagina',$datos))
		{
			$pagina=$datos['pagina'];
			if(isset($datos['order']))
			{
				$order = $datos['order'];
				if(strpos(" ".$order,"-"))
					$orden="desc";
				else
					$orden="asc";
				$order=str_replace("-","",$order); 
			}
			else{
				$order="id"; $orden="asc";
			}
			
			if($pagina == 0)
			{
				$pagina = 1;
			}
			// si existe buscar se realiza esta linea para devolver las filas que en el campo que coincidan con el valor que el usuario escribio
			// si no existe buscar devolver las filas con el limite y la pagina correspondiente a la paginación
			if(array_key_exists('buscar',$datos))
			{
				$columna = $datos['columna'];
				$valor   = $datos['valor'];
				$usuario = Usuario::orderBy($order,$orden);
				
				$search = trim($valor);
				$keyword = $search;
				$usuario=$usuario->whereNested(function($query) use ($keyword)
				{
					
						$query->Where('nombres', 'LIKE', '%'.$keyword.'%')
							 ->orWhere('apellidoPaterno', 'LIKE', '%'.$keyword.'%')
							 ->orWhere('apellidoMaterno', 'LIKE', '%'.$keyword.'%')
							 ->orWhere('email', 'LIKE', '%'.$keyword.'%')
							 ->orWhere('nivel', 'LIKE', '%'.$keyword.'%'); 
				});
				
				$total=$usuario->get();
				$usuario = $usuario->skip($pagina-1)->take($datos['limite'])->get();
			}
			else
			{
				$usuario = Usuario::with("Throttles")->skip($pagina-1)->take($datos['limite'])->orderBy($order,$orden)->get();
				$total=Usuario::all();
			}
			
		}
		else
		{
			$usuario = Usuario::all();
			$total=$usuario;
		}

		if(!$usuario)
		{
			return Response::json(array('status'=> 404,"messages"=>'No hay resultados'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$usuario,"total"=>count($total)),200);
			
		}
	}

	/**
	 * Crear un nuevo registro en la base de datos con los datos enviados
	 *
	 * <h4>Request</h4>
	 * Recibe un input request tipo json de los datos a almacenar en la tabla correspondiente
	 *
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 201, "messages": "Creado", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 500, "messages": "Error interno del servidor"),status) </code>
	 */
	public function store()
	{
		$rules = [
			'email' => 'required|min:3|email'
		];
		$v = \Validator::make(Request::json()->all(), $rules );

		if ($v->fails())
		{
			return Response::json($v->errors());
		}
		$datos = Input::json()->all();
		$success = false;
		
        try 
		{
			$user=array(
				'username' => isset($datos['username']) ? $datos['username'] : explode("@",$datos['email'])[0],
				'nombres' => isset($datos['nombres']) ? $datos['nombres'] : '',
				'apellidoPaterno' => isset($datos['apellidoPaterno']) ? $datos['apellidoPaterno'] : '',
				'apellidoMaterno' => isset($datos['apellidoMaterno']) ? $datos['apellidoMaterno'] : '',
				'cargo' => isset($datos['cargo']) ? $datos['cargo'] : '',
				'telefono' => isset($datos['telefono']) ? $datos['telefono'] :'',
				'email' => $datos['email'],
				'password' => isset($datos['password']) ? $datos['password'] : explode("@",$datos['email'])[0],
				'activated' => 1,
				'permissions'=>isset($datos['permissions']) ? $datos['permissions'] : array(),
				'nivel' => $datos["nivel"]
			);
			

            $usuario = Sentry::createUser($user);

			$role_array = $datos['grupos'];
			if(count($role_array) > 0 && $role_array !== '')
			{
				foreach ($role_array as $rol) 
				{
					$user_group = Sentry::findGroupById($rol);
					$usuario->addGroup($user_group);
				}
			}
			if($datos["nivel"]!=1)
			{
				if(count($datos['UsuarioZona'])>0)
				{
					DB::table('UsuarioZona')->where('idUsuario', "$usuario->id")->delete();				
					DB::table('UsuarioJurisdiccion')->where('idUsuario', "$usuario->id")->delete();
					
					foreach($datos['UsuarioZona'] as $zona)
					{
						if($zona!="")
						{
							if($datos["nivel"]==3)
								DB::table('UsuarioZona')->insert(	array('idUsuario' => "$usuario->id", 'idZona' => $zona["id"]) );	
							if($datos["nivel"]==2)
								DB::table('UsuarioJurisdiccion')->insert(	array('idUsuario' => "$usuario->id", 'jurisdiccion' => $zona["id"]) );	
						}					
					}
				}				
			}
			
            if ($usuario) 
                $success = true;
        } 
		
		catch (\Cartalyst\Sentry\Users\LoginRequiredException $e)
		{
			    return Response::json(array("status"=>400,"messages"=>"Username es requerido"),400);
		}
		catch (\Cartalyst\Sentry\Users\PasswordRequiredException $e)
		{
			    return Response::json(array("status"=>400,"messages"=>"Password es requerido"),400);
		}
		catch (\Cartalyst\Sentry\Users\UserExistsException $e)
		{
			return Response::json(array("status"=>403,"messages"=>"Este nombre de usuario ya existe"),400);
		}
		catch (\Cartalyst\Sentry\Groups\GroupNotFoundException $e)
		{
		    return Response::json(array("status"=>404,"messages"=>"El grupo asignado no existe"),404);
		}
        if ($success) 
		{
			return Response::json(array("status"=>201,"messages"=>"Creado","data"=>$usuario),201);
        } 
		else 
		{
			return Response::json(array("status"=>500,"messages"=>"Error interno del servidor"),500);
        }
		
	}

	/**
	 * Devuelve la información del registro especificado.
	 *
	 * @param  int  $id que corresponde al identificador del recurso a mostrar
	 *
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function show($id)
	{
		$usuario = Usuario::with("Grupos")->find($id);		
		
		if(!$usuario)
		{
			return Response::json(array('status'=> 404,"messages"=>'No hay resultados'),404);
		} 
		else 
		{
			$usuario["nivel"] = $usuario->nivel;
			if($usuario->nivel==2)
			{
				$usuario['UsuarioZona'] = DB::table('UsuarioJurisdiccion')		
				->select(array("jurisdiccion as id","jurisdiccion as nombre"))
				->where('idUsuario',$id)->get();
			}
			else if($usuario->nivel==3)
			{
				$usuario['UsuarioZona'] = DB::table('UsuarioZona AS u')
				->leftJoin('Zona AS c', 'c.id', '=', 'u.idZona')			
				->select('*')
				->where('idUsuario',$id)->get();
			}
			else
				$usuario['UsuarioZona']=array();
			
			
			return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$usuario),200);
		}
	}


	/**
	 * Actualizar el  registro especificado en el la base de datos
	 *
	 * <h4>Request</h4>
	 * Recibe un Input Request con el json de los datos
	 *
	 * @param  int  $id que corresponde al identificador del dato a actualizar 	 
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 304, "messages": "No modificado"),status) </code>
	 */
	public function update($id)
	{
		$datos = Input::json()->all();  
		if(isset($datos['baneo']))
		$rules = [
			'baneo' => 'required'
		];
		else
		$rules = [
			'email' => 'required|min:3|email'
		];
		$v = \Validator::make(Request::json()->all(), $rules );

		if ($v->fails())
		{
			return Response::json($v->errors());
		}
		
		$success = false;
        try 
		{
			if(isset($datos['baneo']))
			{
				$usuario = Sentry::findThrottlerByUserId($id);
				if($usuario->isBanned())
				{
					$usuario->unBan();
				}
				else
				{
					$usuario->ban();
				}
				return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$usuario),200);
			}
			
			$usuario = Sentry::findUserById($id);
			
			$usuario->username = isset($datos['username']) ? $datos['username'] : explode("@",$datos['email'])[0];
			$usuario->nombres = isset($datos['nombres']) ? $datos['nombres'] : '';
			$usuario->apellidoPaterno = isset($datos['apellidoPaterno']) ? $datos['apellidoPaterno'] : '';
			$usuario->apellidoMaterno = isset($datos['apellidoMaterno']) ? $datos['apellidoMaterno'] : '';
			$usuario->cargo = isset($datos['cargo']) ? $datos['cargo'] : '';
			$usuario->telefono = isset($datos['telefono']) ? $datos['telefono'] :'';
			$usuario->email = $datos['email'];				
			$usuario->activated = 1;
			$usuario->nivel = $datos["nivel"];
			
			$user_permission = isset($datos["permissions"]) ? $datos["permissions"] : array();
			
			foreach ($user_permission as $key => $value) 			
			{
				if($value==1)
				{
					$user_permission[$key] = 0;
				}
			}			
			$usuario->permissions = $user_permission;
			
			if(isset($datos['password']))
				if($datos['password'] != "")
					$usuario->password = $datos['password'];
			

            if ($usuario->save()) 
                $success = true;

			$role_array = $datos['grupos'];
			
			$grupos = $usuario->getGroups();
			if(count($grupos)>0)
			{
				foreach ($grupos as $grupo) 
				{
					if(array_search($grupo->id, $role_array) === FALSE)
					{
						$usuario->removeGroup($grupo);
					}
				}
			}
					
			if(count($role_array) > 0 && $role_array !== '')
			{
				foreach ($role_array as $rol) 
				{
					$user_group = Sentry::findGroupById($rol);
					$usuario->addGroup($user_group);
				}
			} 
			if($datos["nivel"]!=1)
			{
				if(count($datos['UsuarioZona'])>0)
				{
					DB::table('UsuarioZona')->where('idUsuario', "$usuario->id")->delete();
					DB::table('UsuarioJurisdiccion')->where('idUsuario', "$usuario->id")->delete();
					
					foreach($datos['UsuarioZona'] as $zona)
					{
						if($zona!="")
						{
							if($datos["nivel"]==3)
								DB::table('UsuarioZona')->insert(	array('idUsuario' => "$usuario->id", 'idZona' => $zona["id"]) );	
							if($datos["nivel"]==2)
								DB::table('UsuarioJurisdiccion')->insert(	array('idUsuario' => "$usuario->id", 'jurisdiccion' => $zona["id"]) );	
						}					
					}
				}				
			}
        } 
		
		catch (\Cartalyst\Sentry\Users\LoginRequiredException $e)
		{
			    return Response::json(array("status"=>400,"messages"=>"Username es requerido"),400);
		}				
		catch (\Cartalyst\Sentry\Groups\GroupNotFoundException $e)
		{
		    return Response::json(array("status"=>404,"messages"=>"El grupo asignado no existe"),404);
		}
        if ($success) 
		{
			return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$usuario),200);
        } 
		else 
		{
			return Response::json(array("status"=>500,"messages"=>"Error interno del servidor"),500);
        }
	}

	/**
	 * Elimine el registro especificado del la base de datos (softdelete).
	 *
	 * @param  int  $id que corresponde al identificador del dato a eliminar
	 *
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 500, "messages": "Error interno del servidor"),status) </code>
	 */
	public function destroy($id)
	{
		$success = false;
        DB::beginTransaction();
        try 
		{
			$usuario = Usuario::find($id);
			$grupos = $usuario->Grupos();
			if(count($grupos)>0)
			{
				foreach ($grupos as $grupo) 
				{
					$usuario->removeGroup($grupo);				
				}
			}
			$usuario->delete();
			
			$success=true;
		} 
		catch (\Exception $e) 
		{
			throw $e;
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$usuario),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}	
	
	/**
	 * Actualiza el perfil del usuario.
	 *
	 * <h4>Request</h4>
	 * Recibe un Input Request con el json de los datos
	 * @param  string  $email que corresponde al identificador del usuario a actualizar. 
	 *	 
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 500, "messages": "Error interno del servidor"),status) </code>
	 */
	public function UpdateInfo($email)
	{
		$datos = Input::json()->all();
		$success = false;
        try 
		{
			$user = Sentry::getUser();
			$usuario = Sentry::findUserByLogin($email);
			
			$usuario->nombres = $datos['nombre'];
			$usuario->apellidoPaterno = $datos['apellido_paterno'];
			$usuario->apellidoMaterno = $datos['apellido_materno'];			
			$usuario->telefono = isset($datos['telefono']) ? $datos['telefono'] : '';					

            if ($usuario->save()) 
                $success = true;			
        } 
		catch (\Cartalyst\Sentry\Users\LoginRequiredException $e)
		{
			    return Response::json(array("status"=>400,"messages"=>"Username es requerido"),400);
		}				
		
        if ($success) 
		{
			return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$usuario),200);
        } 
		else 
		{
			return Response::json(array("status"=>500,"messages"=>"Error interno del servidor"),500);
        }
	}

}