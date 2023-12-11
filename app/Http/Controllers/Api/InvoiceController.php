<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SunatService;
use Illuminate\Http\Request;
use Greenter\Report\XmlUtils;
use Luecano\NumeroALetras\NumeroALetras;

class InvoiceController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'company'=> 'required|array',
            'company.address'=> 'required|array',
            'client'=> 'required|array',
            'details'=> 'required|array',
            'details.*'=> 'required|array',
        ]);

        $data = $request->all();

        $this->setTotales($data);
        $this->setLegends($data);

        $sunat = new SunatService();

        $see = $sunat->getsee();
        $invoice = $sunat->getInvoice($data);
        $result = $see->send($invoice);

        $response['xml'] = $see->getFactory()->getLastXml();
        $response['hash'] = (new XmlUtils)->getHashSign($response['xml']);
        $response['sunatResponse'] = $sunat->sunatResponse($result);

        return response()->json($response, 200);
    }

    public function xml(Request $request)
    {   
        $request->validate([
            'company'=> 'required|array',
            'company.address'=> 'required|array',
            'client'=> 'required|array',
            'details'=> 'required|array',
            'details.*'=> 'required|array',
        ]);

        $data = $request->all();

        $this->setTotales($data);
        $this->setLegends($data);

        $sunat = new SunatService();
        $see = $sunat->getsee();
        $invoice = $sunat->getInvoice($data);

        $response['xml'] = $see->getXmlSigned($invoice);
        $response['hash'] = (new XmlUtils)->getHashSign($response['xml']);

        return response()->json($response, 200);
    }

    public function pdf(Request $request){
        $request->validate([
            'company' => 'required|array',
            'company.address' => 'required|array',
            'client' => 'required|array',
            'details' => 'required|array',
            'details.*' => 'required|array',
        ]);

        $data = $request->all();


        $this->setTotales($data);
        $this->setLegends($data);

        $sunat = new SunatService();
        $see = $sunat->getSee();
        $invoice = $sunat->getInvoice($data);

        $pdf = $sunat->getHtmlReport($invoice);

        return $pdf;
    }

    public function setTotales(&$data)
    {
        $details = collect($data['details']);

        $data['mtoOperGravadas'] = $details->where('tipAfeIgv', 10)->sum('mtoValorVenta');
        $data['mtoOperExoneradas'] = $details->where('tipAfeIgv', 20)->sum('mtoValorVenta');
        $data['mtoOperInafectas'] = $details->where('tipAfeIgv', 30)->sum('mtoValorVenta');
        $data['mtoOperExportacion'] = $details->where('tipAfeIgv', 40)->sum('mtoValorVenta');
        $data['mtoOperGratuitas'] = $details->whereNotIn('tipAfeIgv', [10, 20, 30, 40])->sum('mtoValorVenta');

        $data['mtoIGV'] = $details->whereIn('tipAfeIgv', [10, 20, 30, 40])->sum('igv');
        $data['mtoIGVGratuitas'] = $details->whereNotIn('tipAfeIgv', [10, 20, 30, 40])->sum('igv');
        $data['icbper'] = $details->sum('icbper');
        $data['totalImpuestos'] = $data['mtoIGV'] + $data['icbper'];

        $data['valorVenta'] = $details->whereIn('tipAfeIgv', [10, 20, 30, 40])->sum('mtoValorVenta');
        $data['subTotal'] = $data['valorVenta'] + $data['totalImpuestos'];

        $data['mtoImpVenta'] = floor($data['subTotal'] * 10) / 10;

        $data['redondeo'] = $data['mtoImpVenta'] - $data['subTotal'];
    }

    public function setLegends(&$data)
    {
        $formatter = new NumeroALetras();

        $data['legends'] = [
            [
                'code' => '1000',
                'value' => $formatter->toInvoice($data['mtoImpVenta'], 2, 'SOLES')
            ]
        ];
    }
}
