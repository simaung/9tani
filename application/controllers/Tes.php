<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Tes extends Base_Controller
{

    public function __construct()
    {
        parent::__construct();

        // Load model
        $this->load->model('log_model');
        $firebase = $this->firebase->init();
        $this->db = $firebase->getDatabase();
    }

    function send_email()
    {
        $this->load->library('email');

        $this->email->from('no-reply@sembilantani.com', 'sembilantani.com');
        $this->email->to('simaungproject@gmail.com'); // Ganti dengan email tujuan

        $this->email->subject('tes email');
        $email_body = 'Hai';
        $this->email->message($email_body);

        if ($this->email->send()) {
            echo 'Sukses! email berhasil dikirim.';
        } else {
            echo $this->email->print_debugger(array('headers'));
        }
    }

    function send_email_sukses($email)
    {
        // Konfigurasi email
        $config = [
            'mailtype'  => 'html',
            'charset'   => 'utf-8',
            'protocol'  => 'smtp',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_user' => 'sembilantani.official@gmail.com',  // Email gmail
            'smtp_pass'   => '9tani123!@#',  // Password gmail
            'smtp_crypto' => 'tls',
            'smtp_port'   => 587,
            'crlf'    => "\r\n",
            'newline' => "\r\n"
        ];

        // Load library email dan konfigurasinya
        $this->load->library('email', $config);

        // Email dan nama pengirim
        $this->email->from('no-reply@sembilantani.com', 'sembilantani.com');

        // Email penerima
        $this->email->to($email); // Ganti dengan email tujuan

        // Lampiran email, isi dengan url/path file
        $this->email->attach('https://masrud.com/content/images/20181215150137-codeigniter-smtp-gmail.png');

        // Subject email
        $this->email->subject('Kirim Email dengan SMTP Gmail CodeIgniter | MasRud.com');

        // Isi email
        $this->email->message("Ini adalah contoh email yang dikirim menggunakan SMTP Gmail pada CodeIgniter.<br><br> Klik <strong><a href='https://masrud.com/post/kirim-email-dengan-smtp-gmail' target='_blank' rel='noopener'>disini</a></strong> untuk melihat tutorialnya.");

        // Tampilkan pesan sukses atau error
        if ($this->email->send()) {
            echo 'Sukses! email berhasil dikirim.';
        } else {
            echo $this->email->print_debugger(array('headers'));
            // echo 'Error! email tidak dapat dikirim.';
        }
    }

    function send_email_image()
    {
        $this->load->library('email');

        // $this->load->view('email/payment_success_image');

        $email_body = $this->load->view('email/payment_success_image', '', TRUE);

        $get_email_sender = $this->common_model->get_global_setting(array(
            'group' => 'email',
            'name' => 'post-master'
        ));
        $user_email = 'jackntc@gmail.com';
        $this->email->from($get_email_sender['value'], '9tani');
        $this->email->to($user_email);
        $this->email->bcc('sembilantaniindonesia@gmail.com');

        $this->email->subject('Ucapan terimakasih dari SEMBILAN TANI');
        $this->email->message($email_body);

        $this->email->send();
    }

    function push_notif()
    {
        $this->curl->push('563', 'sample push notif', 'sample_notif', 'menu_aja');
    }

    function push_notif_asli()
    {
        $this->curl->push('f1HEjV0fTXeOY2sjmfl60K:APA91bEQ_Gf3Mu1mCtHuiVy5aCij4MFfxZajfEuGGd2YgsMwjDNP6WIGy3abbswWUNtQs-eZtzshO-HnD2HYnpYZPRn1qkIittBywmrNBeFrUmkULrDY05OUZvoCv1TDZKqzCh2W_NZV', 'ini sample push notif');
    }

    function tes_pdf()
    {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(40, 10, 'Hello World!');
        $pdf->Output();
    }

    function rtb()
    {
        $data = array(
            'akuanakindonesia' => 'wakwaw'
        );
        if (empty($this->data) || !isset($this->data)) {
            return FALSE;
        }

        foreach ($data as $key => $value) {
            $this->db->getReference()->getChild('order')->getChild($key)->set($value);
        }
        return TRUE;
    }
}
