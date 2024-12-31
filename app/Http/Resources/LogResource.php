<?php

namespace App\Http\Resources;

use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $agent = new Agent();
        $agent->setUserAgent($this->user_agent);
        return [
            'id' => $this->id,
            'action' => $this->action,
            'model' => $this->model,
            'data_old' => $this->data_old ? json_decode($this->data_old) : null,
            'data_new' => $this->data_new ? json_decode($this->data_new) : null,
            'description' => $this->description,
            'user_id' => $this->user_id,
            'created_by' => $this->created_by,
            'company_id' => $this->company_id,
            // 'browser' => $agent->browser(),
            // 'browser_version' => $agent->version($agent->browser()),
            // 'platform' => $agent->platform(),
            // 'platform_version' => $agent->version($agent->platform()),
            'user_agent' => $agent->browser() . ' ' .
                $agent->version($agent->browser()) .
                ' (' . $agent->platform() .
                ' ' . $agent->version($agent->platform()) . ')',
            'ip_address' => $this->ip_address,
            'url' => $this->url,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
