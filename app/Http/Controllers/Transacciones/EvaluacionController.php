<?php namespace App\Http\Controllers;

use Response;
use Input;
use DB;
use App\Models\Catalogos\Evaluacion;
use App\Models\Catalogos\Cone;

class EvaluacionController extends Controller 
{
    public function getIndex($id = null) 
    {
     
        if (is_null($id )) 
        {
			$Evaluacion=array();
			foreach(Evaluacion::all()->toArray() as $e)
			{				
				$e['EvaluacionCriterio']=Evaluacion::find($e['idEvaluacion'])->criterios->toArray();
				array_push($Evaluacion,array("Evaluacion"=>$e));
			}
            return Response::json($Evaluacion);
        } 
        else
        {
            $Evaluacion = Evaluacion::find($id);

            if(!$Evaluacion)
            {
                return Response::json(array("Evaluacion"=>'Evaluacion no encontrada','status'=> 404));
            } 
            else 
            {
                return Response::json(array("Evaluacion"=>$Evaluacion));
            }
        }
    }

    public function postIndex() 
    {
		$nuevo = Input::json();
		
        $success = false;
        DB::beginTransaction();
        try 
		{
            $Evaluacion = new Evaluacion;
            $Evaluacion->idClues = 1;
            $Evaluacion->idIndicador = 1;

            if ($Evaluacion->save()) 
			{
                $EvaluacionCriterio = array(array('idCriterio'=>1,'valor'=>'1'),array('idCriterio'=>2,'valor'=>'0'));
                $Evaluacion->Criterios()->sync($EvaluacionCriterio);
                $success = true;
            }
        } 
		catch (\Exception $e) 
		{
            
        }

        if ($success) 
		{
            DB::commit();
            $msg="Success ".$Evaluacion;
            $status=201;
        } 
		else 
		{
            DB::rollback();
            $msg="Error ".$Evaluacion;
            $status=500;  
        }
        
        
        return Response::json(array("messages"=>$msg,"status"=>$status));
    }

    public function putIndex() 
    {
        $actualizarEvaluacion = Input::json();

        $Evaluacion = Evaluacion::find($actualizarEvaluacion->get('idEvaluacion'));
        if(is_null($Evaluacion))
        {
            return Response::json(array("Evaluacion"=>'Evaluacion no encontrada','status'=> 404));
        }
		
		$success = false;
        DB::beginTransaction();
        try 
		{
			$Evaluacion->titulo = $actualizarEvaluacion->get('titulo');
			$Evaluacion->completada = $actualizarEvaluacion->get('completada');
			
			if ($Evaluacion->save()) 
			{
                $EvaluacionCriterio = array(array('idCriterio'=>1,'valor'=>'1'),array('idCriterio'=>2,'valor'=>'0'));
                $Evaluacion->Criterios()->sync($EvaluacionCriterio);
                $success = true;
            }
        } 
		catch (\Exception $e) 
		{
            throw $e;
        }
        if($success)
        {
			DB::commit();
            $msg="Success ".$Evaluacion;
            $status=202;
        }
        else
        {
			DB::rollback();
            $msg="Error ".$Evaluacion;
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