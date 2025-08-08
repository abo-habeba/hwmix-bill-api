<?php

namespace App\Services;

use App\Models\Invoice;

interface DocumentServiceInterface
{
    /**
     * ينشئ مستندًا جديدًا.
     *
     * @param array $data البيانات اللازمة لإنشاء المستند.
     * @return mixed المستند الذي تم إنشاؤه.
     * @throws \Throwable
     */
    public function create(array $data);

    /**
     * يحدث مستندًا موجودًا.
     *
     * @param array $data البيانات الجديدة لتحديث المستند.
     * @param Invoice $invoice المستند (الفاتورة) المراد تحديثه.
     * @return mixed المستند المحدث.
     * @throws \Throwable
     */
    public function update(array $data, Invoice $invoice);

    /**
     * يلغي مستندًا.
     *
     * @param Invoice $invoice المستند (الفاتورة) المراد إلغاؤه.
     * @return Invoice المستند الملغى.
     * @throws \Throwable
     */
    public function cancel(Invoice $invoice): Invoice;
}
