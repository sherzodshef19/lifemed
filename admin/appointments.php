<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
check_role(['admin', 'cashier', 'doctor']);

$page_title = 'Журнал записей';
include '../includes/header.php';
$user_role = $_SESSION['role'];
?>

<div id="app" v-cloak>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <label class="form-label small text-secondary">Выберите дату</label>
                    <input type="date" v-model="selectedDate" @change="fetchAppointments" class="form-control mb-3">
                    <?php if ($user_role !== 'doctor'): ?>
                    <div class="d-grid">
                        <a href="registration.php" class="btn btn-primary"><i class="fas fa-calendar-plus me-2"></i>Новая запись</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-9">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">Приёмы на {{ formatDate(selectedDate) }}</h6>
                    <span class="badge bg-primary rounded-pill"><?= $_SESSION['full_name'] ?> (<?= $user_role == 'doctor' ? 'Врач' : 'Адм' ?>)</span>
                </div>
                <div class="card-body p-0">
                    <div v-if="loading" class="text-center py-5">
                        <div class="spinner-border text-primary"></div>
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Время</th>
                                    <th>Пациент</th>
                                    <?php if ($user_role !== 'doctor'): ?>
                                    <th>Врач</th>
                                    <?php endif; ?>
                                    <th>Услуга</th>
                                    <th>Оплата</th>
                                    <th>Статус</th>
                                    <th class="text-end pe-4">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="group in groupedAppointments" :key="group.ids[0]">
                                    <td class="ps-4 fw-bold text-primary">{{ group.appointment_time ? group.appointment_time.substring(0,5) : '--:--' }}</td>
                                    <td>{{ group.patient_name }}</td>
                                    <?php if ($user_role !== 'doctor'): ?>
                                    <td v-html="group.doctor_name || '<span class=\'text-secondary small\'>Общий приём</span>'"></td>
                                    <?php endif; ?>
                                    <td>
                                        <div v-for="s in group.services" class="mb-1">
                                            <div class="fw-medium text-dark small" style="line-height: 1.2;">
                                                {{ s.name }}
                                                <span v-if="s.qty > 1" class="badge bg-light text-dark border border-secondary border-opacity-10 rounded-pill x-small">x{{ s.qty }}</span>
                                            </div>
                                        </div>
                                        <div class="fw-bold text-primary small mt-1">
                                            {{ formatCurrency(group.services.reduce((s, i) => s + (i.price * i.qty), 0)) }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill px-2" :class="group.payment_status == 'paid' ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-10 text-warning'">
                                            {{ group.payment_status == 'paid' ? 'Оплачено' : 'Долг' }}
                                        </span>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm border-0 bg-light rounded-pill px-3" v-model="group.status" @change="updateStatus(group)">
                                            <option value="scheduled">Ожидает</option>
                                            <option value="completed">Завершен</option>
                                            <option value="cancelled">Отменен</option>
                                        </select>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group">
                                            <a :href="'patient_history.php?id=' + group.patient_id" class="btn btn-sm btn-light text-info" title="История пациента"><i class="fas fa-history"></i></a>
                                            <a :href="'lab_forms.php?patient_id=' + group.patient_id + '&appointment_date=' + group.appointment_date" class="btn btn-sm btn-light text-success" title="Заполнить анализ"><i class="fas fa-file-medical"></i></a>
                                            <button v-if="role !== 'doctor'" class="btn btn-sm btn-light text-dark" title="Печать чека" @click="printReceipt(group)"><i class="fas fa-print"></i></button>
                                            <button v-if="role === 'admin'" class="btn btn-sm btn-light text-danger" title="Удалить запись" @click="deleteAppointment(group.ids)"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="groupedAppointments.length === 0">
                                    <td colspan="7" class="text-center py-5 text-secondary text-opacity-50">
                                        Нет записей на этот день
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .btn-group .btn { padding: 0.25rem 0.5rem; }
</style>

<script>
    const { createApp, ref, computed, onMounted } = Vue;

    createApp({
        setup() {
            const appointments = ref([]);
            const loading = ref(false);
            const selectedDate = ref(new Date().toISOString().split('T')[0]);
            const role = ref('<?= $user_role ?>');

            const fetchAppointments = async () => {
                loading.value = true;
                try {
                    const res = await axios.get(`../api/appointments.php?date=${selectedDate.value}`);
                    appointments.value = res.data.data || res.data || [];
                } catch (e) {
                    showToast('Ошибка загрузки записей', 'danger');
                } finally {
                    loading.value = false;
                }
            };

            const groupedAppointments = computed(() => {
                const groups = {};
                appointments.value.forEach(app => {
                    const key = app.receipt_id || `${app.patient_id}_${app.appointment_time}_${app.doctor_id || 'none'}`;
                    if (!groups[key]) {
                        groups[key] = {
                            ...app,
                            ids: [app.id],
                            services: [{
                                name: app.service_name,
                                qty: app.quantity,
                                price: app.service_price
                            }]
                        };
                    } else {
                        groups[key].ids.push(app.id);
                        groups[key].services.push({
                            name: app.service_name,
                            qty: app.quantity,
                            price: app.service_price
                        });
                        // Total price is calculated by sum of services
                    }
                });
                return Object.values(groups).sort((a, b) => a.appointment_time.localeCompare(b.appointment_time));
            });

            const updateStatus = async (group) => {
                try {
                    const promises = group.ids.map(id => {
                        return axios.post('../api/appointments.php', {
                            ...appointments.value.find(a => a.id === id),
                            id: id,
                            status: group.status
                        });
                    });
                    await Promise.all(promises);
                    showToast('Статус обновлён', 'success');
                } catch (e) {
                    showToast('Ошибка обновления статуса', 'danger');
                }
            };

            const printReceipt = (group) => {
                const url = group.receipt_id 
                    ? `receipt.php?receipt_id=${group.receipt_id}` 
                    : `receipt.php?id=${group.ids.join(',')}`;
                window.open(url, '_blank');
            };

            const deleteAppointment = async (ids) => {
                confirmDelete('Удалить запись?', 'Запись и все услуги в ней будут удалены.', async () => {
                    try {
                        const promises = ids.map(id => axios.delete(`../api/appointments.php?id=${id}`));
                        await Promise.all(promises);
                        showToast('Запись удалена', 'success');
                        fetchAppointments();
                    } catch (e) {
                        showToast('Ошибка при удалении', 'danger');
                    }
                });
            };

            const formatCurrency = (val) => new Intl.NumberFormat('ru-RU').format(val) + ' сум';
            const formatDate = (dateStr) => new Date(dateStr).toLocaleDateString('ru-RU', { 
                day: 'numeric', 
                month: 'long', 
                year: 'numeric' 
            });

            onMounted(fetchAppointments);

            return { 
                appointments, 
                groupedAppointments,
                selectedDate, 
                role,
                loading,
                updateStatus, 
                printReceipt, 
                deleteAppointment,
                formatCurrency, 
                formatDate, 
                fetchAppointments 
            };
        }
    }).mount('#app');
</script>

<?php include '../includes/footer.php'; ?>
