<?php

return [
    'success' => 'Başarılı',
    'created' => 'Oluşturuldu',
    'updated' => 'Güncellendi.',
    'deleted' => 'Silindi',
    'ping' => 'Taqsim API çalışıyor',

    'errors' => [
        'subscription_required' => 'Abonelik süresi doldu. Devam etmek için bir paket seçin.',
        'plan_limit_reached' => 'Paket limitine ulaşıldı. Daha yüksek bir pakete geçin.',
        'insufficient_balance' => 'Bakiye yetersiz. Lütfen bakiyenizi yükleyin.',
        'yearly_not_available' => 'Bu planda yıllık abonelik mevcut değil.',
        'generic' => 'Hata',
        'unauthenticated' => 'Oturum açılmadı.',
        'validation_failed' => 'Veriler geçersiz.',
        'not_found' => 'Kayıt bulunamadı.',
        'not_found_http' => 'Sayfa bulunamadı.',
        'forbidden' => 'Erişim yok.',
        'forbidden_shop' => 'Bu işletmeye erişim yok.',
        'forbidden_shop_bakery' => 'Bu fırına erişim yok.',
        'forbidden_owner_only' => 'Bu işlemi yalnızca sahip yapabilir.',
        'forbidden_permission' => 'Bu bölüm için yetkiniz yok.',
        'rate_limit' => 'Çok fazla istek. Lütfen bekleyin.',
        'server_error' => 'Sunucu hatası.',
        'invalid_expense_category' => 'Geçersiz gider kategorisi veya size ait değil.',
        'expense_category_duplicate' => 'Bu isimde bir kategori zaten var veya sistem kategorisiyle çakışıyor.',
        'return_production_mismatch' => 'Seçilen parti ürün türü veya tarihle uyuşmuyor.',
    ],

    'auth' => [
        'send_code_phone_exists' => 'Bu numara zaten kayıtlı. Kod gönderildi.',
        'send_code_new' => 'Doğrulama kodu gönderildi.',
        'register_phone_taken' => 'Bu telefon zaten kayıtlı. Lütfen giriş yapın.',
        'invalid_code' => 'Kod hatalı veya süresi dolmuş.',
        'register_success' => 'Kayıt tamamlandı.',
        'login_invalid' => 'Telefon veya şifre hatalı.',
        'login_success' => 'Giriş yaptınız.',
        'profile_updated' => 'Profil güncellendi.',
        'avatar_updated' => 'Avatar güncellendi.',
        'avatar_removed' => 'Avatar kaldırıldı.',
        'password_changed' => 'Şifre değiştirildi.',
        'account_deleted' => 'Hesap silindi.',
        'logout_success' => 'Çıkış yaptınız.',
        'device_revoked' => 'Cihaz çıkarıldı.',
    ],

    'shop' => [
        'created' => 'İşletme başarıyla oluşturuldu.',
        'deleted' => 'İşletme silindi.',
    ],

    'recipe' => [
        'duplicate_bread_category' => 'Bu ürün kategorisi için tarif zaten mevcut.',
    ],

    'employees' => [
        'code_sent' => 'Doğrulama kodu çalışanın numarasına gönderildi.',
        'created' => 'Çalışan eklendi.',
        'phone_taken' => 'Bu numara zaten kayıtlı. Başka bir numara girin.',
        'invite_expired' => 'Davet süresi doldu. Tekrar deneyin.',
        'seat_suspended' => 'Çalışan koltuğu ödeme süresi doldu. Bakiyenizi yükleyin.',
        'owner_subscription_inactive' => 'Sahibin aboneliği sona erdi. Sahip ile iletişime geçin.',
    ],
];
