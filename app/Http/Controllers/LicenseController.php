<?php
/**
 * CatLab Drinks - Simple bar automation system
 * Copyright (C) 2019 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    /**
     * Handle the license return redirect after a license purchase.
     * Validates the license key, applies it to the device, and redirects to the devices page.
     */
    public function applyLicense(Request $request)
    {
        $deviceId = $request->input('device_id');
        $licenseKey = $request->input('license');

        if (!$deviceId || !$licenseKey) {
            return redirect('/manage/devices');
        }

        $organisation = \Auth::user()->organisations()->first();
        if (!$organisation) {
            return redirect('/manage/devices');
        }

        $device = Device::where('id', $deviceId)
            ->where('organisation_id', $organisation->id)
            ->first();

        if (!$device) {
            return redirect('/manage/devices');
        }

        $error = $this->validateLicenseKey($licenseKey, $device);
        if ($error) {
            return redirect('/manage/devices');
        }

        $device->license_key = $licenseKey;
        $device->save();

        return redirect('/manage/devices');
    }

    /**
     * Validate the license key structure and match against the device.
     * @param string $licenseKey
     * @param Device $device
     * @return string|null Error message or null if valid
     */
    private function validateLicenseKey(string $licenseKey, Device $device): ?string
    {
        $decoded = base64_decode($licenseKey, true);
        if ($decoded === false) {
            return 'Invalid license key: not valid base64.';
        }

        $license = json_decode($decoded, true);
        if (!is_array($license) || !isset($license['data'])) {
            return 'Invalid license key: invalid license structure.';
        }

        if (!isset($license['signature']) || empty($license['signature'])) {
            return 'Invalid license key: missing signature.';
        }

        $data = $license['data'];
        if (!is_array($data) || !isset($data['device_uid'])) {
            return 'Invalid license key: missing device_uid in license data.';
        }

        if ($data['device_uid'] !== $device->uid) {
            return 'Invalid license key: device_uid does not match this device.';
        }

        if (isset($data['expiration_date']) && $data['expiration_date'] !== null) {
            $expirationDate = strtotime($data['expiration_date']);
            if ($expirationDate === false) {
                return 'Invalid license key: invalid expiration_date format.';
            }

            if ($expirationDate < time()) {
                return 'Invalid license key: license has expired.';
            }
        }

        return null;
    }
}
