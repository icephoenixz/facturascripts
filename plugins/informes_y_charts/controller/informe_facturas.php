<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'base/fs_pdf.php';
require_model('factura_cliente.php');
require_model('factura_proveedor.php');

class informe_facturas extends fs_controller
{
   public $desde;
   public $factura_cli;
   public $factura_pro;
   public $hasta;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Facturas', 'informes', FALSE, TRUE);
   }
   
   protected function process()
   {
      $this->desde = Date('1-m-Y');
      $this->factura_cli = new factura_cliente();
      $this->factura_pro = new factura_proveedor();
      $this->hasta = Date('d-m-Y', mktime(0, 0, 0, date("m")+1, date("1")-1, date("Y")));
      
      if( isset($_POST['listado']) )
      {
         if($_POST['listado'] == 'facturascli')
            $this->listar_facturas_cli();
         else
            $this->listar_facturas_prov();
      }
   }
   
   private function listar_facturas_cli()
   {
      /// desactivamos el motor de plantillas
      $this->template = FALSE;
      
      $pdf_doc = new fs_pdf('a4', 'landscape', 'Courier');
      $pdf_doc->pdf->addInfo('Title', 'Facturas emitidas del '.$_POST['dfecha'].' al '.$_POST['hfecha'] );
      $pdf_doc->pdf->addInfo('Subject', 'Facturas emitidas del '.$_POST['dfecha'].' al '.$_POST['hfecha'] );
      $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
      
      $facturas = $this->factura_cli->all_desde($_POST['dfecha'], $_POST['hfecha']);
      if($facturas)
      {
         $total_lineas = count($facturas);
         $linea_actual = 0;
         $lppag = 31;
         $total = $re = 0;
         $bases = array();
         $impuestos = array();
         $pagina = 1;
         
         while($linea_actual < $total_lineas)
         {
            if($linea_actual > 0)
            {
               $pdf_doc->pdf->ezNewPage();
               $pagina++;
            }
            
            /// encabezado
            $pdf_doc->pdf->ezText($this->empresa->nombre." - Facturas emitidas del ".$_POST['dfecha']." al ".$_POST['hfecha'].":\n\n", 14);
            
            /// tabla principal
            $pdf_doc->new_table();
            $pdf_doc->add_table_header(
               array(
                   'serie' => '<b>S</b>',
                   'factura' => '<b>Fact.</b>',
                   'asiento' => '<b>Asi.</b>',
                   'fecha' => '<b>Fecha</b>',
                   'subcuenta' => '<b>Subcuenta</b>',
                   'descripcion' => '<b>Descripción</b>',
                   'cifnif' => '<b>'.FS_CIFNIF.'</b>',
                   'base' => '<b>Base Im.</b>',
                   'iva' => '<b>% IVA</b>',
                   'totaliva' => '<b>IVA</b>',
                   're' => '<b>% RE</b>',
                   'totalre' => '<b>RE</b>',
                   'total' => '<b>Total</b>'
               )
            );
            for($i = 0; $i < $lppag AND $linea_actual < $total_lineas; $i++)
            {
               $linea = array(
                   'serie' => $facturas[$linea_actual]->codserie,
                   'factura' => $facturas[$linea_actual]->numero,
                   'asiento' => '-',
                   'fecha' => $facturas[$linea_actual]->fecha,
                   'subcuenta' => '-',
                   'descripcion' => $facturas[$linea_actual]->nombrecliente,
                   'cifnif' => $facturas[$linea_actual]->cifnif,
                   'base' => 0,
                   'iva' => 0,
                   'totaliva' => 0,
                   're' => $facturas[$linea_actual]->recfinanciero,
                   'totalre' => $facturas[$linea_actual]->totalrecargo,
                   'total' => 0
               );
               $asiento = $facturas[$linea_actual]->get_asiento();
               if($asiento)
               {
                  $linea['asiento'] = $asiento->numero;
                  $partidas = $asiento->get_partidas();
                  if($partidas)
                     $linea['subcuenta'] = $partidas[0]->codsubcuenta;
               }
               $linivas = $facturas[$linea_actual]->get_lineas_iva();
               if($linivas)
               {
                  $nueva_linea = FALSE;
                  
                  foreach($linivas as $liva)
                  {
                     /// acumulamos la base
                     if( !isset($bases[$liva->iva]) )
                        $bases[$liva->iva] = $liva->neto;
                     else
                        $bases[$liva->iva] += $liva->neto;
                     
                     /// acumulamos el iva
                     if( !isset($impuestos[$liva->iva]) )
                        $impuestos[$liva->iva] = $liva->totaliva;
                     else
                        $impuestos[$liva->iva] += $liva->totaliva;
                     
                     /// completamos y añadimos la línea al PDF
                     $linea['base'] = $this->show_numero($liva->neto);
                     $linea['iva'] = $this->show_numero($liva->iva);
                     $linea['totaliva'] = $this->show_numero($liva->totaliva);
                     $linea['total'] = $this->show_numero($liva->totallinea);
                     $pdf_doc->add_table_row($linea);
                     
                     if($nueva_linea)
                        $i++;
                     else
                        $nueva_linea = TRUE;
                  }
               }
               
               $re += $facturas[$linea_actual]->recfinanciero;
               $total += $facturas[$linea_actual]->total;
               $linea_actual++;
            }
            $pdf_doc->save_table(
               array(
                   'fontSize' => 8,
                   'cols' => array(
                       'base' => array('justification' => 'right'),
                       'iva' => array('justification' => 'right'),
                       'totaliva' => array('justification' => 'right'),
                       're' => array('justification' => 'right'),
                       'totalre' => array('justification' => 'right'),
                       'total' => array('justification' => 'right')
                   ),
                   'shaded' => 0,
                   'width' => 750
               )
            );
            $pdf_doc->pdf->ezText("\n", 10);
            
            
            /// Rellenamos la última tabla
            $pdf_doc->new_table();
            $titulo = array('pagina' => '<b>Suma y sigue</b>');
            $fila = array('pagina' => $pagina . '/' . ceil($total_lineas / $lppag));
            $opciones = array(
                'cols' => array('base' => array('justification' => 'right')),
                'showLines' => 0,
                'width' => 750
            );
            foreach($bases as $i => $value)
            {
               $titulo['base'.$i] = '<b>Base '.$i.'%</b>';
               $fila['base'.$i] = $this->show_precio($value);
               $opciones['cols']['base'.$i] = array('justification' => 'right');
            }
            foreach($impuestos as $i => $value)
            {
               $titulo['iva'.$i] = '<b>IVA '.$i.'%</b>';
               $fila['iva'.$i] = $this->show_precio($value);
               $opciones['cols']['iva'.$i] = array('justification' => 'right');
            }
            $titulo['re'] = '<b>RE</b>';
            $titulo['total'] = '<b>Total</b>';
            $fila['re'] = $this->show_precio($re);
            $fila['total'] = $this->show_precio($total);
            $opciones['cols']['re'] = array('justification' => 'right');
            $opciones['cols']['total'] = array('justification' => 'right');
            $pdf_doc->add_table_header($titulo);
            $pdf_doc->add_table_row($fila);
            $pdf_doc->save_table($opciones);
         }
      }
      else
      {
         $pdf_doc->pdf->ezText($this->empresa->nombre." - Facturas emitidas del ".$_POST['dfecha']." al ".$_POST['hfecha'].":\n\n", 14);
         $pdf_doc->pdf->ezText("Ninguna.\n\n", 14);
      }
      
      $pdf_doc->show();
   }
   
   private function listar_facturas_prov()
   {
      /// desactivamos el motor de plantillas
      $this->template = FALSE;
      
      $pdf_doc = new fs_pdf('a4', 'landscape', 'Courier');
      $pdf_doc->pdf->addInfo('Title', 'Facturas emitidas del '.$_POST['dfecha'].' al '.$_POST['hfecha'] );
      $pdf_doc->pdf->addInfo('Subject', 'Facturas emitidas del '.$_POST['dfecha'].' al '.$_POST['hfecha'] );
      $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
      
      $facturas = $this->factura_pro->all_desde($_POST['dfecha'], $_POST['hfecha']);
      if($facturas)
      {
         $total_lineas = count( $facturas );
         $linea_actual = 0;
         $lppag = 31;
         $total = $re = 0;
         $bases = array();
         $impuestos = array();
         $pagina = 1;

         while($linea_actual < $total_lineas)
         {
            if($linea_actual > 0)
            {
               $pdf_doc->pdf->ezNewPage();
               $pagina++;
            }
            
            /// encabezado
            $pdf_doc->pdf->ezText($this->empresa->nombre." - Facturas recibidas del ".$_POST['dfecha'].' al '.$_POST['hfecha'].":\n\n", 14);
            
            /// tabla principal
            $pdf_doc->new_table();
            $pdf_doc->add_table_header(
               array(
                   'serie' => '<b>S</b>',
                   'factura' => '<b>Fact.</b>',
                   'asiento' => '<b>Asi.</b>',
                   'fecha' => '<b>Fecha</b>',
                   'subcuenta' => '<b>Subcuenta</b>',
                   'descripcion' => '<b>Descripción</b>',
                   'cifnif' => '<b>'.FS_CIFNIF.'</b>',
                   'base' => '<b>Base Im.</b>',
                   'iva' => '<b>% IVA</b>',
                   'totaliva' => '<b>IVA</b>',
                   're' => '<b>% RE</b>',
                   'totalre' => '<b>RE</b>',
                   'total' => '<b>Total</b>'
               )
            );
            for($i = 0; $i < $lppag AND $linea_actual < $total_lineas; $i++)
            {
               $linea = array(
                   'serie' => $facturas[$linea_actual]->codserie,
                   'factura' => $facturas[$linea_actual]->numero,
                   'asiento' => '-',
                   'fecha' => $facturas[$linea_actual]->fecha,
                   'subcuenta' => '-',
                   'descripcion' => $facturas[$linea_actual]->nombre,
                   'cifnif' => $facturas[$linea_actual]->cifnif,
                   'base' => 0,
                   'iva' => 0,
                   'totaliva' => 0,
                   're' => $facturas[$linea_actual]->recfinanciero,
                   'totalre' => $facturas[$linea_actual]->totalrecargo,
                   'total' => 0
               );
               $asiento = $facturas[$linea_actual]->get_asiento();
               if($asiento)
               {
                  $linea['asiento'] = $asiento->numero;
                  $partidas = $asiento->get_partidas();
                  if($partidas)
                     $linea['subcuenta'] = $partidas[0]->codsubcuenta;
               }
               $linivas = $facturas[$linea_actual]->get_lineas_iva();
               if($linivas)
               {
                  $nueva_linea = FALSE;
                  
                  foreach($linivas as $liva)
                  {
                     /// acumuluamos la base
                     if( !isset($bases[$liva->iva]) )
                        $bases[$liva->iva] = $liva->neto;
                     else
                        $bases[$liva->iva] += $liva->neto;
                     
                     /// acumulamos el iva
                     if( !isset($impuestos[$liva->iva]) )
                        $impuestos[$liva->iva] = $liva->totaliva;
                     else
                        $impuestos[$liva->iva] += $liva->totaliva;
                     
                     /// completamos y añadimos la línea al pdf
                     $linea['base'] = $this->show_numero($liva->neto);
                     $linea['iva'] = $this->show_numero($liva->iva);
                     $linea['totaliva'] = $this->show_numero($liva->totaliva);
                     $linea['total'] = $this->show_numero($liva->totallinea);
                     $pdf_doc->add_table_row($linea);
                     
                     
                     if($nueva_linea)
                        $i++;
                     else
                        $nueva_linea = TRUE;
                  }
               }
               
               $re += $facturas[$linea_actual]->recfinanciero;
               $total += $facturas[$linea_actual]->total;
               $linea_actual++;
            }
            $pdf_doc->save_table(
               array(
                   'fontSize' => 8,
                   'cols' => array(
                       'base' => array('justification' => 'right'),
                       'iva' => array('justification' => 'right'),
                       'totaliva' => array('justification' => 'right'),
                       're' => array('justification' => 'right'),
                       'totalre' => array('justification' => 'right'),
                       'total' => array('justification' => 'right')
                   ),
                   'shaded' => 0,
                   'width' => 750
               )
            );
            $pdf_doc->pdf->ezText("\n", 10);
            
            
            /// Rellenamos la última tabla
            $pdf_doc->new_table();
            $titulo = array('pagina' => '<b>Suma y sigue</b>');
            $fila = array('pagina' => $pagina . '/' . ceil($total_lineas / $lppag));
            $opciones = array(
                'cols' => array('base' => array('justification' => 'right')),
                'showLines' => 0,
                'width' => 750
            );
            foreach($bases as $i => $value)
            {
               $titulo['base'.$i] = '<b>BASE '.$i.'%</b>';
               $fila['base'.$i] = $this->show_precio($value);
               $opciones['cols']['base'.$i] = array('justification' => 'right');
            }
            foreach($impuestos as $i => $value)
            {
               $titulo['iva'.$i] = '<b>IVA '.$i.'%</b>';
               $fila['iva'.$i] = $this->show_precio($value);
               $opciones['cols']['iva'.$i] = array('justification' => 'right');
            }
            $titulo['re'] = '<b>RE</b>';
            $titulo['total'] = '<b>Total</b>';
            $fila['re'] = $this->show_precio($re);
            $fila['total'] = $this->show_precio($total);
            $opciones['cols']['re'] = array('justification' => 'right');
            $opciones['cols']['total'] = array('justification' => 'right');
            $pdf_doc->add_table_header($titulo);
            $pdf_doc->add_table_row($fila);
            $pdf_doc->save_table($opciones);
         }
      }
      else
      {
         $pdf_doc->pdf->ezText($this->empresa->nombre." - Facturas recibidas del ".$_POST['dfecha'].' al '.$_POST['hfecha'].":\n\n", 14);
         $pdf_doc->pdf->ezText("Ninguna.\n\n", 14);
      }
      
      $pdf_doc->show();
   }
   
   public function stats_best_clients()
   {
      $stats = array();
      $stats_cli = $this->stats_last_days_aux('facturascli');
      $stats_pro = $this->stats_last_days_aux('facturasprov');
      
      foreach($stats_cli as $i => $value)
      {
         $stats[$i] = array(
             'day' => $value['day'],
             'total_cli' => $value['total'],
             'total_pro' => 0
         );
      }
      
      foreach($stats_pro as $i => $value)
         $stats[$i]['total_pro'] = $value['total'];
      
      return $stats;
   }
   
   public function stats_best_clients_aux($table_name='facturascli', $numdays = 25)
   {
      $stats = array();
      $desde = Date('d-m-Y', strtotime( Date('d-m-Y').'-'.$numdays.' day'));
      
      foreach($this->date_range($desde, Date('d-m-Y'), '+1 day', 'd') as $date)
      {
         $i = intval($date);
         $stats[$i] = array('day' => $i, 'total' => 0);
      }
      
      if( strtolower(FS_DB_TYPE) == 'postgresql')
         $sql_aux = "to_char(fecha,'FMDD')";
      else
         $sql_aux = "DATE_FORMAT(fecha, '%d')";
      
      $data = $this->db->select("SELECT ".$sql_aux." as dia, sum(total) as total
         FROM ".$table_name." WHERE fecha >= ".$this->empresa->var2str($desde)."
         AND fecha <= ".$this->empresa->var2str(Date('d-m-Y'))."
         GROUP BY ".$sql_aux." ORDER BY dia ASC;");
      if($data)
      {
         foreach($data as $d)
         {
            $i = intval($d['dia']);
            $stats[$i] = array(
                'day' => $i,
                'total' => floatval($d['total'])
            );
         }
      }
      return $stats;
   }
   
   public function stats_last_days()
   {
      $stats = array();
      $stats_cli = $this->stats_last_days_aux('facturascli');
      $stats_pro = $this->stats_last_days_aux('facturasprov');
      
      foreach($stats_cli as $i => $value)
      {
         $stats[$i] = array(
             'day' => $value['day'],
             'total_cli' => $value['total'],
             'total_pro' => 0
         );
      }
      
      foreach($stats_pro as $i => $value)
         $stats[$i]['total_pro'] = $value['total'];
      
      return $stats;
   }
   
   public function stats_last_days_aux($table_name='facturascli', $numdays = 25)
   {
      $stats = array();
      $desde = Date('d-m-Y', strtotime( Date('d-m-Y').'-'.$numdays.' day'));
      
      foreach($this->date_range($desde, Date('d-m-Y'), '+1 day', 'd') as $date)
      {
         $i = intval($date);
         $stats[$i] = array('day' => $i, 'total' => 0);
      }
      
      if( strtolower(FS_DB_TYPE) == 'postgresql')
         $sql_aux = "to_char(fecha,'FMDD')";
      else
         $sql_aux = "DATE_FORMAT(fecha, '%d')";
      
      $data = $this->db->select("SELECT ".$sql_aux." as dia, sum(total) as total
         FROM ".$table_name." WHERE fecha >= ".$this->empresa->var2str($desde)."
         AND fecha <= ".$this->empresa->var2str(Date('d-m-Y'))."
         GROUP BY ".$sql_aux." ORDER BY dia ASC;");
      if($data)
      {
         foreach($data as $d)
         {
            $i = intval($d['dia']);
            $stats[$i] = array(
                'day' => $i,
                'total' => floatval($d['total'])
            );
         }
      }
      return $stats;
   }
   
   public function stats_last_months()
   {
      $stats = array();
      $stats_cli = $this->stats_last_months_aux('facturascli');
      $stats_pro = $this->stats_last_months_aux('facturasprov');
      $meses = array(
          1 => 'ene',
          2 => 'feb',
          3 => 'mar',
          4 => 'abr',
          5 => 'may',
          6 => 'jun',
          7 => 'jul',
          8 => 'ago',
          9 => 'sep',
          10 => 'oct',
          11 => 'nov',
          12 => 'dic'
      );
      
      foreach($stats_cli as $i => $value)
      {
         $stats[$i] = array(
             'month' => $meses[ $value['month'] ],
             'total_cli' => round($value['total'], 2),
             'total_pro' => 0
         );
      }
      
      foreach($stats_pro as $i => $value)
         $stats[$i]['total_pro'] = round($value['total'], 2);
      
      return $stats;
   }
   
   public function stats_last_months_aux($table_name='facturascli', $num = 11)
   {
      $stats = array();
      $desde = Date('d-m-Y', strtotime( Date('01-m-Y').'-'.$num.' month'));
      
      foreach($this->date_range($desde, Date('d-m-Y'), '+1 month', 'm') as $date)
      {
         $i = intval($date);
         $stats[$i] = array('month' => $i, 'total' => 0);
      }
      
      if( strtolower(FS_DB_TYPE) == 'postgresql')
         $sql_aux = "to_char(fecha,'FMMM')";
      else
         $sql_aux = "DATE_FORMAT(fecha, '%m')";
      
      $data = $this->db->select("SELECT ".$sql_aux." as mes, sum(total) as total
         FROM ".$table_name." WHERE fecha >= ".$this->empresa->var2str($desde)."
         AND fecha <= ".$this->empresa->var2str(Date('d-m-Y'))."
         GROUP BY ".$sql_aux." ORDER BY mes ASC;");
      if($data)
      {
         foreach($data as $d)
         {
            $i = intval($d['mes']);
            $stats[$i] = array(
                'month' => $i,
                'total' => floatval($d['total'])
            );
         }
      }
      return $stats;
   }
   
   public function stats_last_years()
   {
      $stats = array();
      $stats_cli = $this->stats_last_years_aux('facturascli');
      $stats_pro = $this->stats_last_years_aux('facturasprov');
      
      foreach($stats_cli as $i => $value)
      {
         $stats[$i] = array(
             'year' => $value['year'],
             'total_cli' => round($value['total'], 2),
             'total_pro' => 0
         );
      }
      
      foreach($stats_pro as $i => $value)
         $stats[$i]['total_pro'] = round($value['total'], 2);
      
      return $stats;
   }
   
   public function stats_last_years_aux($table_name='facturascli', $num = 4)
   {
      $stats = array();
      $desde = Date('d-m-Y', strtotime( Date('d-m-Y').'-'.$num.' year'));
      
      foreach($this->date_range($desde, Date('d-m-Y'), '+1 year', 'Y') as $date)
      {
         $i = intval($date);
         $stats[$i] = array('year' => $i, 'total' => 0);
      }
      
      if( strtolower(FS_DB_TYPE) == 'postgresql')
         $sql_aux = "to_char(fecha,'FMYYYY')";
      else
         $sql_aux = "DATE_FORMAT(fecha, '%Y')";
      
      $data = $this->db->select("SELECT ".$sql_aux." as ano, sum(total) as total
         FROM ".$table_name." WHERE fecha >= ".$this->empresa->var2str($desde)."
         AND fecha <= ".$this->empresa->var2str(Date('d-m-Y'))."
         GROUP BY ".$sql_aux." ORDER BY ano ASC;");
      if($data)
      {
         foreach($data as $d)
         {
            $i = intval($d['ano']);
            $stats[$i] = array(
                'year' => $i,
                'total' => floatval($d['total'])
            );
         }
      }
      return $stats;
   }
   
   private function date_range($first, $last, $step = '+1 day', $format = 'd-m-Y' )
   {
      $dates = array();
      $current = strtotime($first);
      $last = strtotime($last);
      
      while( $current <= $last )
      {
         $dates[] = date($format, $current);
         $current = strtotime($step, $current);
      }
      
      return $dates;
   }
}
