<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipmentPeriodController extends Controller
{
    public function update(Request $request) { return $this->change($request, false); }
    public function destroy(Request $request) { return $this->change($request, true); }

    private function change(Request $request, bool $delete)
    {
        $rules = ['asset_id' => ['required','integer','exists:company_asset_tbl,asset_id'], 'period' => ['required','date_format:Y-m']];
        if (!$delete) {
            $rules['target_asset_id'] = ['required','integer','exists:company_asset_tbl,asset_id'];
            $rules['target_period'] = ['required','date_format:Y-m'];
        }
        $data = $request->validate($rules);
        return DB::transaction(function () use ($data, $delete) {
            $assetIds = [$data['asset_id']];
            if (!$delete) $assetIds[] = $data['target_asset_id'];
            $assets = DB::table('company_asset_tbl')->whereIn('asset_id', $assetIds)->orderBy('asset_id')->lockForUpdate()->get();
            if ($assets->contains(fn ($asset) => $asset->asset_type !== 'heavy_equipment')) {
                throw ValidationException::withMessages(['asset_id' => 'Select a heavy equipment asset.']);
            }
            $start = Carbon::createFromFormat('!Y-m', $data['period']);
            $expenses = DB::table('fin_equipment_expense_tbl')->where('asset_id', $data['asset_id'])->where('expense_date','>=',$start->toDateString())->where('expense_date','<',$start->copy()->addMonth()->toDateString());
            $rentals = DB::table('fin_equipment_rental_income_tbl')->where('asset_id', $data['asset_id'])->whereDate('period_month', $start->toDateString());
            $expenseRows = (clone $expenses)->lockForUpdate()->get();
            $rentalRows = (clone $rentals)->lockForUpdate()->get();
            if ($expenseRows->isEmpty() && $rentalRows->isEmpty()) abort(404, 'No underlying records found for this asset and period.');
            if ($delete) {
                $expenses->delete(); $rentals->delete();
            } else {
                $target = Carbon::createFromFormat('!Y-m', $data['target_period']);
                if ($target->greaterThan(today())) throw ValidationException::withMessages(['target_period' => 'The period cannot be in the future.']);
                if ($data['asset_id'] != $data['target_asset_id'] || $data['period'] !== $data['target_period']) {
                    $occupied = DB::table('fin_equipment_expense_tbl')->where('asset_id',$data['target_asset_id'])->where('expense_date','>=',$target->toDateString())->where('expense_date','<',$target->copy()->addMonth()->toDateString())->exists()
                        || DB::table('fin_equipment_rental_income_tbl')->where('asset_id',$data['target_asset_id'])->whereDate('period_month',$target->toDateString())->exists();
                    if ($occupied) throw ValidationException::withMessages(['target_period' => 'This asset already has records for the selected period. Choose another asset or period.']);
                }
                foreach ($expenseRows as $row) {
                    $date = $target->copy()->day(min(Carbon::parse($row->expense_date)->day, $target->daysInMonth));
                    if ($date->greaterThan(today())) throw ValidationException::withMessages(['target_period' => 'Moving this period would create a future expense date.']);
                    DB::table('fin_equipment_expense_tbl')->where('equip_expense_id',$row->equip_expense_id)->update(['asset_id'=>$data['target_asset_id'],'expense_date'=>$date->toDateString()]);
                }
                $rentals->update(['asset_id'=>$data['target_asset_id'],'period_month'=>$target->toDateString()]);
            }
            return response()->json(['message' => $delete ? 'Equipment period deleted.' : 'Equipment period updated.', 'expenses' => $expenseRows->count(), 'rentals' => $rentalRows->count()]);
        });
    }
}
