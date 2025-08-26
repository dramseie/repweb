<?php
        namespace App\Controller;
        use App\Service\DynamicQueryService;
        use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
        use Symfony\Component\HttpFoundation\JsonResponse;
        use Symfony\Component\HttpFoundation\Request;
        use Symfony\Component\Routing\Annotation\Route;
        use Symfony\Component\HttpFoundation\StreamedResponse;
        use PhpOffice\PhpSpreadsheet\Spreadsheet;
        use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
        class DataTableController extends AbstractController {
          #[Route('/data-table', name:'data_table_index')] public function index(): \Symfony\Component\HttpFoundation\Response { return $this->render('data_table/index.html.twig'); }
          #[Route('/data-table/data', name:'data_table_data')] public function data(Request $request, DynamicQueryService $svc): JsonResponse {
            $u=$this->getUser(); $role=$u?->getRoles()[0]??'ROLE_USER';
            $search=$request->query->get('search')['value']??null; $start=(int)$request->query->get('start',0);
            $length=(int)$request->query->get('length',10); $orderCol=$request->query->get('order')[0]['column']??0; $orderDir=$request->query->get('order')[0]['dir']??'asc';
            return $this->json($svc->getData($role,$search,$start,$length,$orderCol,$orderDir));
          }
          #[Route('/data-table/export/{format}', name:'data_table_export')] public function export(string $format, Request $req, DynamicQueryService $svc): StreamedResponse {
            $role=$this->getUser()?->getRoles()[0]??'ROLE_USER'; $rows=$svc->getAllData($role,$req->query->get('search')); $fn="export_".date('Ymd_His').".$format";
            if($format==='csv'){ $resp=new StreamedResponse(function() use($rows){$h=fopen('php://output','w'); if(!empty($rows)){ fputcsv($h,array_keys($rows[0])); foreach($rows as $r){ fputcsv($h,$r);} } fclose($h);}); $resp->headers->set('Content-Type','text/csv'); }
            else { $ss=new Spreadsheet(); $sh=$ss->getActiveSheet(); if(!empty($rows)){ $sh->fromArray(array_keys($rows[0]),null,'A1'); $sh->fromArray($rows,null,'A2'); }
                   $w=new Xlsx($ss); $resp=new StreamedResponse(function() use($w){ $w->save('php://output');}); $resp->headers->set('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); }
            $resp->headers->set('Content-Disposition', 'attachment; filename="' . $fn . '"'); return $resp;
          }
        }
        