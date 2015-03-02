<?php namespace App\Http\Controllers;

use Response;
use Input;
use App\Tarea;
class TareasController extends Controller 
{

    public $restful = true;

    public function getIndex($id = null) 
    {
        if (is_null($id )) 
        {
            return Response::json(array("tareas"=>Tarea::all()));
        } 
        else
        {
            $tarea = Tarea::find($id);

            if(!$tarea)
            {
                return Response::json(array("tareas"=>'Tarea no encontrada','status'=> 404));
            } 
            else 
            {
                return Response::json(array("tareas"=>$tarea));
            }
        }
    }

    public function postIndex() 
    {
        $nuevatarea = Input::json();

        $tarea = new Tarea();
        $tarea->titulo = $nuevatarea->get('titulo');
        $tarea->completada = $nuevatarea->get('completada');
        if($tarea->save())
        {
            $msg="Success ".$tarea;
            $status=201;
        }
        else
        {
            $msg="Error ".$tarea;
            $status=500;   
        }
        
        return Response::json(array("msg"=>$msg,"status"=>$status));
    }

    public function putIndex() 
    {
        $actualizartarea = Input::json();

        $tarea = Tarea::find($actualizartarea->get('id'));
        if(is_null($tarea))
        {
            return Response::json(array("tareas"=>'Tarea no encontrada','status'=> 404));
        }
        $tarea->titulo = $actualizartarea->get('titulo');
        $tarea->completada = $actualizartarea->get('completada');
        if($tarea->save())
        {
            $msg="Success ".$tarea;
            $status=202;
        }
        else
        {
            $msg="Error ".$tarea;
            $status=500;   
        }
        
        return Response::json(array("msg"=>$msg,"status"=>$status));
    }

    public function deleteIndex($id = null) 
    {
        $tarea = Tarea::find($id);

        if(is_null($tarea))
        {
            return Response::json(array("tareas"=>'Tarea no encontrada','status'=> 404));
        }
        $tareaeliminada = $tarea;
           
        if($tarea->delete())
        {
            $msg="Success ".$tarea;
            $status=202;
        }
        else
        {
            $msg="Error ".$tarea;
            $status=500;   
        }  
        return Response::json(array("msg"=>$msg,"status"=>$status));
    } 
}

?>