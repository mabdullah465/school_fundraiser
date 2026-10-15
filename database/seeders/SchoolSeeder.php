<?php

namespace Database\Seeders;

use App\Enums\SchoolStatus;
use App\Models\School;
use Illuminate\Database\Seeder;

/**
 * Dummy schools. The first ten mirror the Figma wireframes. Ids are fixed so the Shopify
 * theme (and your notes) can refer to the same school across database resets.
 */
class SchoolSeeder extends Seeder
{
    /** @return string a valid, stable 26 char ULID-shaped id */
    public static function ulid(int $n): string
    {
        return '01JB'.str_pad((string) $n, 22, '0', STR_PAD_LEFT);
    }

    public function run(): void
    {
        $figma = [
            ['Lincoln High School', 'lincoln.school@example.test', 'Los Angeles', 'CA', SchoolStatus::Approved],
            ['Washington Elementary School', 'washington.elementary@example.test', 'Seattle', 'WA', SchoolStatus::Approved],
            ['Roosevelt Middle School', 'roosevelt.middle@example.test', 'Denver', 'CO', SchoolStatus::Rejected],
            ['Jefferson Academy', 'jefferson.academy@example.test', 'Austin', 'TX', SchoolStatus::Pending],
            ['Maplewood High School', 'maplewood.school@example.test', 'Portland', 'OR', SchoolStatus::Approved],
            ['Green Valley School', 'green.valley@example.test', 'Phoenix', 'AZ', SchoolStatus::Rejected],
            ['Riverdale High School', 'riverdale.school@example.test', 'Columbus', 'OH', SchoolStatus::Pending],
            ['Oak Ridge Academy', 'ridge.academy@example.test', 'Nashville', 'TN', SchoolStatus::Approved],
            ['Westfield Public School', 'westfield.school@example.test', 'Boston', 'MA', SchoolStatus::Rejected],
            ['Sunnybrook Elementary', 'sunnybrook.elementary@example.test', 'Miami', 'FL', SchoolStatus::Pending],
        ];

        // Extra approved schools so search and pagination have something to chew on.
        $extra = [
            ['Cedar Grove High School', 'Atlanta', 'GA'], ['Hillcrest Elementary', 'Chicago', 'IL'],
            ['Lakeside Academy', 'Minneapolis', 'MN'], ['Pinecrest Middle School', 'Charlotte', 'NC'],
            ['Brookfield High School', 'Detroit', 'MI'], ['Summit Ridge School', 'Salt Lake City', 'UT'],
            ['Meadowbrook Elementary', 'Kansas City', 'MO'], ['Fairview Academy', 'Raleigh', 'NC'],
            ['Highland Park High School', 'Dallas', 'TX'], ['Stonebridge Middle School', 'San Diego', 'CA'],
            ['Willow Creek Elementary', 'Sacramento', 'CA'], ['Northgate High School', 'Seattle', 'WA'],
            ['Eastwood Academy', 'Pittsburgh', 'PA'], ['Harborview School', 'Baltimore', 'MD'],
            ['Redwood Elementary', 'San Jose', 'CA'], ['Silver Lake High School', 'Los Angeles', 'CA'],
            ['Crestview Middle School', 'Tampa', 'FL'], ['Oakmont Academy', 'Indianapolis', 'IN'],
            ['Prairie Hills School', 'Omaha', 'NE'], ['Mountain View High School', 'Boise', 'ID'],
        ];

        $n = 1;

        foreach ($figma as [$name, $email, $city, $state, $status]) {
            $this->make($n++, $name, $email, $city, $state, $status);
        }

        foreach ($extra as [$name, $city, $state]) {
            $this->make($n++, $name, str($name)->slug('.').'@example.test', $city, $state, SchoolStatus::Approved);
        }

        // Approved once, then switched off: must not appear in the public list.
        $this->make($n++, 'Former Partner Academy', 'former.partner@example.test', 'Reno', 'NV', SchoolStatus::Deactivated);
    }

    private function make(int $n, string $name, string $email, string $city, string $state, SchoolStatus $status): void
    {
        School::updateOrCreate(['ulid' => self::ulid($n)], [
            'name' => $name,
            'email' => $email,
            'phone' => '+1 555 010 '.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'address' => "{$n}C, 14th street",
            'city' => $city,
            'state' => $state,
            'country' => 'US',
            'status' => $status,
            'approved_at' => $status === SchoolStatus::Approved ? now() : null,
            'deactivated_at' => $status === SchoolStatus::Deactivated ? now() : null,
            'rejection_reason' => $status === SchoolStatus::Rejected ? 'Dummy data: documents not provided.' : null,
        ]);
    }
}
