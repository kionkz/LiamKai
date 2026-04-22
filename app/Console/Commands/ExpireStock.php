<?php

namespace App\Console\Commands;

use App\Models\Inventory;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireStock extends Command
{
    protected $signature   = 'inventory:expire-stock';
    protected $description = 'Mark expired stock batches as losses and deduct from inventory';

    public function handle(): int
    {
        $today = now()->toDateString();

        // Find all un-expired stock_in purchase receipt batches whose expiration date has passed
        $expiredBatches = StockMovement::where('type', 'stock_in')
            ->where('movement_type', 'purchase_receipt')
            ->where('expired', false)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<=', $today)
            ->get();

        if ($expiredBatches->isEmpty()) {
            $this->info('No expired stock batches found.');
            return self::SUCCESS;
        }

        $count = 0;

        foreach ($expiredBatches as $batch) {
            DB::beginTransaction();
            try {
                $inventory = Inventory::where('product_id', $batch->product_id)->first();

                if (!$inventory) {
                    $batch->update(['expired' => true]);
                    DB::commit();
                    continue;
                }

                $qty = (float) ($batch->remaining_quantity ?? $batch->quantity);

                if ($qty <= 0) {
                    $batch->update(['expired' => true, 'remaining_quantity' => 0]);
                    DB::commit();
                    continue;
                }

                // Record as stock_out / expired
                StockMovement::create([
                    'product_id'     => $batch->product_id,
                    'quantity'       => $qty,
                    'type'           => 'stock_out',
                    'movement_type'  => 'expired',
                    'reason'         => 'Stock expired',
                    'reference'      => $batch->reference,
                    'reference_id'   => $batch->reference_id,
                    'source_stock_movement_id' => $batch->id,
                    'notes'          => "Expired batch from {$batch->expiration_date->toDateString()} — automatically recorded as loss",
                    'expiration_date'=> $batch->expiration_date,
                    'expired'        => true,
                ]);

                // Mark original batch as processed
                $batch->update(['expired' => true, 'remaining_quantity' => 0]);
                $inventory->syncQuantityFromBatches();

                DB::commit();
                $count++;
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error("ExpireStock: failed for stock_movement #{$batch->id}", ['error' => $e->getMessage()]);
            }
        }

        $this->info("Expired {$count} stock batch(es) and deducted from inventory.");
        return self::SUCCESS;
    }
}
