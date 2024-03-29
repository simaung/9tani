<?php
defined('BASEPATH') or exit('No direct script access allowed');


class Send
{

    public function __construct()
    {
        // Assign the CodeIgniter super-object
        $this->CI = &get_instance();
    }

    public function index($trans, $phone, $name, $invoice_code = '', $layanan = '', $durasi = '', $nominal = '', $tipe = '', $keterangan = '')
    {
        $phone   = preg_replace('/^(\+62|62|0)?/', "62", $phone);

        switch ($trans) {
            case 'order':
                $postData = $this->order($phone, $name, $invoice_code, $layanan, $durasi);
                break;
            case 'paid9massage':
                $postData = $this->paid9massage($phone, $name, $invoice_code, $layanan, $durasi);
                break;
            case 'paid9clean':
                $postData = $this->paid9clean($phone, $name, $invoice_code, $layanan, $durasi);
                break;
            case 'finish':
                $postData = $this->finish($phone, $name);
                break;
            case 'saldo':
                $postData = $this->saldo($phone, $name, $nominal, $tipe, $keterangan);
                break;
            case 'banktransfer':
                $postData = $this->banktransfer($phone, $name, $nominal);
                break;
            case 'sendOtp':
                $postData = $this->sendotp($phone, $name, $invoice_code);
                break;
            case 'withdrawsuccess':
                $postData = $this->withdrawsuccess($phone, $name, $invoice_code, $layanan, $durasi);
                break;
            case 'invalid_account':
                $postData = $this->invalid_account($phone, $name, $invoice_code, $layanan, $durasi);
                break;
            case 'orderExpired':
                $postData = $this->orderexpired($phone, $invoice_code, $layanan, $durasi);
                break;
            default:
                break;
        }
        $this->kirim($postData);
    }

    public function send_file($trans, $phone, $name, $link_file)
    {
        $phone   = preg_replace('/^(\+62|62|0)?/', "62", $phone);
        switch ($trans) {
            case 'callback_flip':
                $postData = $this->callbackflip($phone, $name, $link_file);
                break;
            default:
                break;
        }
        $this->kirim_file($postData);
    }

    public function kirim($postData)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.chat-api.com/instance166681/sendMessage?token=bnw54hpjqk3f8q86",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        // echo $response;
    }

    public function kirim_file($postData)
    {

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.chat-api.com/instance166681/sendFile?token=bnw54hpjqk3f8q86",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
    }

    private function order($phone, $customer, $invoice, $layanan, $durasi)
    {

        $postData = array(
            'phone' => $phone,
            'body' => "Yth. *" . ucwords($customer) . "*\n\nTerimakasih sudah melakukan pemesanan di Platform kami Sembilan Kita - Tiga serangkai solusi yang menghadirkan kebutuhan konsumen (sandang, pangan dan papan) untuk menghadapi fase kehidupan new normal.\n\nInvoice number :\n $invoice\nLayanan :\n $layanan\nDurasi :\n $durasi\n\nJika metode pembayaran yang dipilih adalah Online (Bank Transfer, Virtual Account, Retail Via : Indomaret/Alfamart dll), silahkan melakukan pembayaran 30 menit maksimal setelah proses pemesanan dilakukan. Pemesanan akan di batalkan secara otomatis jika pembayaran tersebut tidak dilakukan.\n\nJika metoda pembayaran yang dipilih adalah Tunai / COD, maka pembayaran dapat dilakukan setelah pesanan di selesaikan.\n\nJika Customer mengalami kendala pada saat pemesanan, tim kami siap membantu dan silahkan menghubungi Customer Service kami di *0812-2090-4936* (whatsapp/telegram).\n\nTerimakasih,\nSembilan Kita \n\n\"Berbagi Manfaat Kehidupan\n\n*Mohon Tidak Membalas Pesan Ini - No Reply*.\""
        );
        return json_encode($postData);
    }

    private function paid9massage($phone, $customer, $invoice, $layanan, $durasi)
    {

        $postData = array(
            'phone' => $phone,
            'body' => "Yth. *" . ucwords($customer) . "*\n\nTerimakasih sudah melakukan pembayaran, pesanan anda sudah kami aktifkan sesuai dengan jadwal dan mitra terbaik kami yang terpilih. \n\nInvoice number :\n $invoice\nLayanan :\n $layanan\nDurasi :\n $durasi\n\nSembilan Massage adalah layanan pijat profesional keluarga, bagian dari Sembilan Kita (PT Pradana Dharma Bakti). Selain menjamin kualitas pelayanan dan kepuasan Customer, Sembilan Massage turut menempatkan keamanan dan perlindungan para pengguna dan mitra sebagai prioritas utama.\n\nSembilan Kita akan menindak tegas segala pelanggaran di luar ketentuan layanan yang bisa membahayakan keamanan mitra maupun pengguna, termasuk tindak *pelecehan seksual verbal maupun non-verbal*, sesuai dengan prosedur hukum & Undang-Undang yang berlaku di Indonesia dengan ancaman *denda dan/atau penjara maksimal 2 tahun*.\n\n- Pasal 315 KUHP - Penghinaan  \n- Pasal 281 KUHP - Pelanggaran Kesusilaan\n- Pasal 289 - 296 KUHP - Perbuatan Cabul / Pelecehan Sexual\n\nJika Customer mengalami kendala pada layanan kami, tim kami siap membantu dan silahkan menghubungi Customer Service kami di *0812-2090-4936* (whatsapp/telegram).\n\nTerimakasih,\nSembilan Kita \n\n\"Berbagi Manfaat Kehidupan\n\n*Mohon Tidak Membalas Pesan Ini - No Reply*.\""
        );
        return json_encode($postData);
    }

    private function paid9clean($phone, $customer, $invoice, $layanan, $durasi)
    {

        $postData = array(
            'phone' => $phone,
            'body' => "Yth. *" . ucwords($customer) . "*\n\nTerimakasih sudah melakukan pembayaran, pesanan anda sudah kami aktifkan sesuai dengan jadwal dan mitra terbaik kami yang terpilih. \n\nInvoice number :\n $invoice\nLayanan :\n $layanan\nDurasi :\n $durasi\n\nSembilan Clean adalah jasa layanan kebersihan profesional yang merupakan bagian dari Sembilan Kita (PT Pradana Dharma Bakti). Selain menjamin kualitas pelayanan dan kepuasan Customer, Sembilan Clean turut menempatkan keamanan dan perlindungan para pengguna dan mitra sebagai prioritas utama.\n\nSembilan Kita akan menindak tegas segala pelanggaran di luar ketentuan layanan yang bisa membahayakan keamanan mitra maupun pengguna, termasuk tindak *pelecehan verbal maupun non-verbal*, sesuai dengan prosedur hukum & Undang-Undang yang berlaku di Indonesia dengan ancaman *denda dan/atau penjara maksimal 2 tahun*.\n\n- Pasal 315 KUHP - Penghinaan  \n- Pasal 281 KUHP - Pelanggaran Kesusilaan\n- Pasal 289 - 296 KUHP - Perbuatan Cabul / Pelecehan Sexual\n\nJika Customer mengalami kendala pada layanan kami, tim kami siap membantu dan silahkan menghubungi Customer Service kami di *0812-2090-4936* (whatsapp/telegram).\n\nTerimakasih,\nSembilan Kita \n\n\"Berbagi Manfaat Kehidupan\n\n*Mohon Tidak Membalas Pesan Ini - No Reply*.\""
        );
        return json_encode($postData);
    }

    private function finish($phone, $customer)
    {

        $postData = array(
            'phone' => $phone,
            'body' => "Yth. *" . ucwords($customer) . "*\n\nTERIMAKASIH\natas dukungan Anda kepada SEMBILAN KITA dan INDONESIA.\n\nDengan membeli produk SEMBILAN KITA, Anda telah mendukung industri UKM di Indonesia serta menyediakan lapangan pekerjaan untuk tenaga kerja dalam negeri yang merupakan Mitra SEMBILAN KITA.\n\nTetap dukung dan cintai produk Indonesia !\n\nFollow juga instagram kami *@sembilankita* / kunjungi website kami di *https://sembilankita.com* untuk informasi terkini seputar produk dan promo.\n\nJika pelayanan yang telah anda terima mengalami masalah, silahkan menghubungi teknikal customer service kami di *0812-2090-4936* (whatsapp/telegram).\n\nTerimakasih,\nSembilan Kita \n\n\"Berbagi Manfaat Kehidupan\n\n*Mohon Tidak Membalas Pesan Ini - No Reply*.\""
        );
        return json_encode($postData);
    }

    private function newmember($phone, $customer)
    {

        $postData = array(
            'phone' => $phone,
            'body' => "Yth. *" . ucwords($customer) . "*\n\nTerimakasih sudah melakukan pendaftaran di Platform kami Sembilan Kita - Tiga serangkai solusi yang menghadirkan kebutuhan konsumen (sandang, pangan dan papan) untuk menghadapi fase kehidupan new normal.\n\nTetap dukung dan cintai produk Indonesia !\n\nFollow juga instagram kami *@sembilankita* / kunjungi website kami di *https://sembilankita.com* untuk informasi terkini seputar produk dan promo.\n\nJika anda mengalami kendala pada saat pemesanan, tim kami siap membantu dan silahkan menghubungi Customer Service kami di *0812-2090-4936* (whatsapp/telegram).\n\nTerimakasih,\nSembilan Kita \n\n\"Berbagi Manfaat Kehidupan\n\n*Mohon Tidak Membalas Pesan Ini - No Reply*.\""
        );
        return json_encode($postData);
    }

    private function banktransfer($phone, $customer, $nominal)
    {

        $postData = array(
            'phone' => $phone,
            'body' => "Yth. *" . ucwords($customer) . "*\n\nSilakan lakukan pembayaran sebesar Rp. *" . number_format($nominal, 2, ',', '.') . "*, Harap disertai kode unik sesuai dengan nominal yang tertera ke salah satu akun dibawah ini.\n\n*BNI : 0968341543 a.n Wishnu Satria Adhita*\n*BCA : 4372519901 a.n Wishnu Satria Adhita*\n\nJika anda mengalami kendala, tim kami siap membantu dan silahkan menghubungi Customer Service kami di *0812-2090-4936* (whatsapp/telegram).\n\nTerimakasih,\nSembilan Kita \n\n\"Berbagi Manfaat Kehidupan\n\n*Mohon Tidak Membalas Pesan Ini - No Reply*.\""
        );
        return json_encode($postData);
    }

    private function saldo($phone, $customer, $nominal, $tipe, $keterangan)
    {

        $postData = array(
            'phone' => $phone,
            'body' => "Yth. *" . ucwords($customer) . "*\n\nSaldo akun anda $tipe sebesar Rp. *" . number_format($nominal, 2, ',', '.') . "* pada " . date('d F Y H:i:s') . "\n\n*\"$keterangan\"*\n\nTerimakasih,\nSembilan Kita \n\n\"Berbagi Manfaat Kehidupan\n\n*Mohon Tidak Membalas Pesan Ini - No Reply*.\""
        );
        return json_encode($postData);
    }

    private function sendotp($phone, $otp, $type)
    {
        if ($type == 'register') {
            $message = "Sembilankita - *$otp* adalah kode verifikasi akun sembilankita anda. \nPENTING: Demi keamanan akun Anda, mohon tidak menyebarkan kode ini kepada siapa pun.";
        } elseif ($type == 'login') {
            $message = "Sembilankita - *$otp* adalah kode untuk masuk ke aplikasi sembilankita anda. \nPENTING: Demi keamanan akun Anda, mohon tidak menyebarkan kode ini kepada siapa pun.";
        } else {
            $message = "Sembilankita - *$otp* adalah kode untuk verifikasi nomor telepon anda. \nPENTING: Demi keamanan akun Anda, mohon tidak menyebarkan kode ini kepada siapa pun.";
        }
        $postData = array(
            'phone' => $phone,
            'body' => $message
        );
        return json_encode($postData);
    }

    private function withdrawsuccess($phone, $customer, $amount, $bank_name, $bank_account_holder)
    {
        $postData = array(
            'phone' => $phone,
            'body' => "Yth. *" . ucwords($customer) . "*\n\nProses pengambilan saldo anda sudah berhasil diproses sebesar Rp. *" . number_format($amount, 2, ',', '.') . "* ke bank $bank_name a.n $bank_account_holder.\n\nTerimakasih,\nSembilan Kita \n\n\"Berbagi Manfaat Kehidupan\n\n*Mohon Tidak Membalas Pesan Ini - No Reply*.\""
        );
        return json_encode($postData);
    }

    private function callbackflip($phone, $name, $link_file)
    {
        $postData = array(
            'phone' => $phone,
            'body' => $link_file,
            'filename'  => date('Y-m-d H:i:s') . ".jpg"
        );

        return json_encode($postData);
    }

    private function invalid_account($phone, $customer, $bank_name, $bank_account_no, $bank_account_holder)
    {
        $postData = array(
            'phone' => $phone,
            'body' => "Yth. *" . ucwords($customer) . "*\n\nMohon maaf data rekening bank yang anda berikan kepada kami, yaitu rekening:\n\nNama bank *$bank_name*\nNomor rekening *$bank_account_no*\nAtas nama *$bank_account_holder*\n\n*TIDAK VALID*.\n\nSilakan hubungi admin terkait informasi ini\n\nTerimakasih,\nSembilan Kita \n\n\"Berbagi Manfaat Kehidupan\n\n*Mohon Tidak Membalas Pesan Ini - No Reply*.\""
        );
        return json_encode($postData);
    }

    private function orderexpired($phone, $invoice, $customer_kelamin, $lokasi)
    {
        if ($customer_kelamin == 'P') {
            $customer = 'WANITA';
        } elseif ($customer_kelamin == 'L') {
            $customer = 'PRIA';
        } else {
            $customer = '-';
        }
        $postData = array(
            'phone' => $phone,
            'body' => "*Potensi Customer*\n\nWilayah *" . ltrim($lokasi) . "* *Customer $customer Order* namun tidak ada yg merespond.\n\nDihimbau agar para *MITRA $customer* terdekat dapat merespond order yang terjadi.\n\nSalam,\n\n*9Kita MGM*"
        );

        return json_encode($postData);
    }
}
