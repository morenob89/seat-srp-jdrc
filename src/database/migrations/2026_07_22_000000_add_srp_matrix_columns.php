<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the Flat / Matrix mode fields:
 *   - srp_type  : operation type column chosen at request time (peacetime|strategic)
 *   - srp_class : payout-matrix row key (e.g. t1_cruiser, logi_t2_cruiser, pod)
 *
 * Both are nullable so existing rows (created under Simple/Advanced modes) and
 * those modes going forward are unaffected.
 */
class AddSrpMatrixColumns extends Migration
{
    public function up()
    {
        Schema::table('cryptatech_seat_srp_srp', function (Blueprint $table) {
            $table->string('srp_type')->nullable()->after('cost');
            $table->string('srp_class')->nullable()->after('srp_type');
        });

        Schema::table('cryptatech_seat_quotes', function (Blueprint $table) {
            $table->string('srp_type')->nullable()->after('value');
            $table->string('srp_class')->nullable()->after('srp_type');
        });
    }

    public function down()
    {
        Schema::table('cryptatech_seat_srp_srp', function (Blueprint $table) {
            $table->dropColumn(['srp_type', 'srp_class']);
        });

        Schema::table('cryptatech_seat_quotes', function (Blueprint $table) {
            $table->dropColumn(['srp_type', 'srp_class']);
        });
    }
}
