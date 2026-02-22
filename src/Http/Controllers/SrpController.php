<?php

namespace CryptaTech\Seat\SeatSrp\Http\Controllers;

use CryptaTech\Seat\SeatSrp\Helpers\SrpManager;
use CryptaTech\Seat\SeatSrp\Models\KillMail;
use CryptaTech\Seat\SeatSrp\Models\Quote;
use CryptaTech\Seat\SeatSrp\Validation\AddKillMail;
use Illuminate\Http\Request;
use Seat\Eveapi\Jobs\Killmails\Detail;
use Seat\Eveapi\Models\Killmails\Killmail as EveKillmail;
use Seat\Eveapi\Models\Killmails\KillmailDetail;
use Seat\Web\Http\Controllers\Controller;

class SrpController extends Controller
{

    use SrpManager;

    public function srpGetRequests()
    {
        $kills = KillMail::where('user_id', auth()->user()->id)
            ->orderby('created_at', 'desc')
            ->take(20)
            ->get();

        return view('srp::request', compact('kills'));
    }

    private function getKillmailDetails(Request $request): array
    {
        // The submitted url is available at $request->km;
        $url_parts = explode('/', trim($request->km, "/ \t\n\r\0\x0B"));

        $token = $url_parts[count($url_parts) - 2];
        $hash = $url_parts[count($url_parts) - 1];

        $killmail = EveKillmail::firstOrCreate([
            'killmail_id' => $token,
        ], [
            'killmail_hash' => $hash,
        ]);

        if (! KillmailDetail::find($killmail->killmail_id))
        {
            Detail::dispatchSync($killmail->killmail_id, $killmail->killmail_hash);
        }

        $totalKill = [];

        $totalKill = array_merge($totalKill, $this->srpPopulateSlots($killmail));
        preg_match('/([a-z0-9]{35,42})/', $request->km, $tokens);
        $totalKill['killToken'] = $tokens[0];

        return $totalKill;
    }

    // This will return raw information regarding the killmail.
    public function srpGetKillMail(Request $request)
    {

        $totalKill = $this->getKillmailDetails($request);

        if ($totalKill['price']['error'] !== 'None') {
            return redirect()->back()->with('error', $totalKill['price']['error']);
        }

        return response()->json($totalKill);
    }

    // This is called by the user to request a SRP quote for their loss.
    public function srpRequestQuote(Request $request)
    {
        $totalKill = $this->getKillmailDetails($request);

        if ($totalKill['price']['error'] !== 'None') {
            return redirect()->back()->with('error', $totalKill['price']['error']); // THIS DOESNT WORK!!
        }

        $quote = Quote::firstOrCreate(
            ['killmail_id' => $totalKill['killId']],
            ['user' => $request->user()->id, 'value' => $totalKill['price']['price']]
        );
        $quote->update();

        $totalKill['quoteID'] = $quote->id;

        return response()->json($totalKill);
    }

    public function srpSaveKillMail(AddKillMail $request)
    {
        $quote = Quote::find($request->input('srpQuoteID'));

        if ($quote->user !== $request->user()->id){
            return redirect()->back()->with('error', 'SRP Quote can only be accepted by creating user.');
        }

        KillMail::create([
            'user_id' => $quote->user,
            'character_name' => $request->input('srpCharacterName'),
            'kill_id' => $quote->killmail_id,
            'kill_token' => $request->input('srpKillToken'),
            'approved' => 0,
            'cost' => $quote->value,
            'type_id' => $request->input('srpTypeId'),
            'ship_type' => $request->input('srpShipType'),
        ]);

        if (! is_null($request->input('srpPingContent')) && $request->input('srpPingContent') != '')
            KillMail::addNote($request->input('srpKillId'), 'ping', $request->input('srpPingContent'));

        $quote->delete();

        return redirect()->back()
            ->with('success', trans('srp::srp.submitted'));
    }

    public function getInsurances($kill_id)
    {
        $killmail = KillMail::where('kill_id', $kill_id)->first();

        if (is_null($killmail))
            return response()->json(['msg' => sprintf('Unable to retried killmail %s', $kill_id)], 404);

        $data = [];

        foreach ($killmail->type->insurances as $insurance) {

            array_push($data, [
                'name' => $insurance->name,
                'cost' => $insurance->cost,
                'payout' => $insurance->payout,
                'refunded' => $insurance->refunded(),
                'remaining' => $insurance->remaining($killmail),
            ]);
        }

        return response()->json($data);
    }

    public function getPing($kill_id)
    {
        $killmail = KillMail::find($kill_id);

        if (is_null($killmail))
            return response()->json(['msg' => sprintf('Unable to retrieve kill %s', $kill_id)], 404);

        if (! is_null($killmail->ping()))
            return response()->json($killmail->ping());

        return response()->json(['msg' => sprintf('There are no ping information related to kill %s', $kill_id)], 204);
    }

    public function getReason($kill_id)
    {
        $killmail = KillMail::find($kill_id);

        if (is_null($killmail))
            return response()->json(['msg' => sprintf('Unable to retrieve kill %s', $kill_id)], 404);

        if (! is_null($killmail->reason()))
            return response()->json($killmail->reason());

        return response()->json(['msg' => sprintf('There is no reason information related to kill %s', $kill_id)], 204);
    }

    public function getAboutView()
    {
        return view('srp::about');
    }

    public function getInstructionsView()
    {
        return view('srp::instructions');
    }
}
