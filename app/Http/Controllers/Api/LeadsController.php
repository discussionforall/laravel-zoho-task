<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class LeadsController extends Controller
{

    private $prospects = '';

    function __construct()
    {
        $url = config('zoho.ZOHO_TOKEN_REST_API_URL');

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url);

        $this->prospects = $response->json();
    }

    public function index()
    {
        $url = config('zoho.ZOHO_BASE_URL') . '/Prospects';

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Zoho-oauthtoken ' . $this->prospects['access_token'],
        ])->get($url, [
            'sort_by' => 'Created_Time',
            'sort_order' => 'desc',
            'fields' => 'Full_Name,Email',
            'per_page' => 5
        ]);

        $prospects = $response->json();

        return response()->json($prospects);
    }

    public function createProspect(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'First_Name' => 'required|string',
            'Name' => 'required|string',
            'Mobile' => 'required|regex:/^04\d{8}$/',
            'Email' => 'required|email',
            'DOB' => 'required|date_format:Y-m-d',
            'Tax_File_Number' => 'required|digits:9|numeric',
            'Agreed_Terms' => 'required|in:Yes,No',
            'Status' => 'required|in:Ready For Search,New Prospect',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $url = config('zoho.ZOHO_BASE_URL') . '/Prospects';

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $this->prospects['access_token'],
        ])->post($url, [
            'data' => [
                [
                    'First_Name' => $request->First_Name,
                    'Name' => $request->Name,
                    'Mobile' => $request->Mobile,
                    'Email' => $request->Email,
                    'DOB' => $request->DOB,
                    'Tax_File_Number' => $request->Tax_File_Number,
                    'Agreed_Terms' => $request->Agreed_Terms,
                    'Status' => $request->Status,
                ]
            ]
        ]);

        $createdProspect = $response->json();

        if (isset($createdProspect['data']) && $createdProspect['data'][0]['code'] == "SUCCESS") {
            $prospectId = $createdProspect['data'][0]['details']['id'];
            $prospectName = $createdProspect['data'][0]['details']['Created_By']['name'];
            $prospectEmail = $request->Email;

            // Send email
            $emailTo = 'it@truewealth.com.au';
            $emailSubject = 'New Prospect Notification';
            $emailBody = "Below are details about new created prospect.<br><br>";
            $emailBody .= "ID: $prospectId<br>";
            $emailBody .= "Name: $prospectName<br>";
            $emailBody .= "Email: $prospectEmail<br>";
            $emailBody .= "Link to prospect's details: https://crmsandbox.zoho.com.au/crm/newff/tab/CustomModule1/$prospectId";

            mail($emailTo, $emailSubject, $emailBody);

            return response()->json($createdProspect);
        } else {
            return response()->json(['error' => 'Failed to create prospect'], 500);
        }
    }
}
