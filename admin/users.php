<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
check_role(['admin']);

$page_title = 'Управление пользователями';
include '../includes/header.php';
?>

<div id="app" v-cloak>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold mb-0 text-secondary">Сотрудники системы</h5>
        <button class="btn btn-primary shadow-sm" @click="openModal()">
            <i class="fas fa-user-plus me-2"></i>Добавить пользователя
        </button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-secondary small text-uppercase">
                    <tr>
                        <th class="ps-4">Имя</th>
                        <th>Логин</th>
                        <th>Роль</th>
                        <th>Дата создания</th>
                        <th class="text-end pe-4">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="5" class="text-center py-4">
                            <div class="spinner-border text-primary"></div>
                        </td>
                    </tr>
                    <tr v-for="user in users" :key="user.id">
                        <td class="ps-4">
                            <div class="fw-bold">{{ user.full_name }}</div>
                        </td>
                        <td><code class="text-primary">{{ user.username }}</code></td>
                        <td>
                            <span class="badge" :class="user.role == 'admin' ? 'bg-danger' : 'bg-info'">
                                {{ user.role.toUpperCase() }}
                            </span>
                        </td>
                        <td class="text-secondary small">{{ formatDate(user.created_at) }}</td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-light text-primary me-2" @click="openModal(user)"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-light text-danger" @click="deleteUser(user.id)" :disabled="user.username == 'admin'"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ editingId ? 'Редактировать' : 'Новый' }} пользователь</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form @submit.prevent="saveUser">
                        <div class="mb-3">
                            <label class="form-label">Полное имя</label>
                            <input type="text" v-model="form.full_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Логин</label>
                            <input type="text" v-model="form.username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Пароль {{ editingId ? '(оставьте пустым для сохранения текущего)' : '' }}</label>
                            <input type="password" v-model="form.password" class="form-control" :required="!editingId" minlength="1" maxlength="64">
                            <div class="form-text">От 1 до 64 символов</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Роль</label>
                            <select v-model="form.role" class="form-select" required>
                                <option value="admin">Администратор</option>
                                <option value="cashier">Кассир</option>
                            </select>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary py-2">Сохранить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const { createApp, ref, onMounted } = Vue;

    createApp({
        setup() {
            const users = ref([]);
            const loading = ref(false);
            const editingId = ref(null);
            const form = ref({ full_name: '', username: '', password: '', role: 'cashier' });

            const fetchUsers = async () => {
                try {
                    const res = await axios.get('../api/users.php');
                    users.value = res.data.data || res.data;
                } catch (e) {
                    showToast('Ошибка загрузки пользователей', 'danger');
                }
            };

            const openModal = (user = null) => {
                if (user) {
                    editingId.value = user.id;
                    form.value = { ...user, password: '' };
                } else {
                    editingId.value = null;
                    form.value = { full_name: '', username: '', password: '', role: 'cashier' };
                }
                bootstrap.Modal.getOrCreateInstance(document.getElementById('userModal')).show();
            };

            const saveUser = async () => {
                if (!editingId.value && (form.value.password.length < 1 || form.value.password.length > 64)) {
                    showToast('Пароль должен быть от 1 до 64 символов', 'warning');
                    return;
                }
                if (editingId.value && form.value.password && (form.value.password.length < 1 || form.value.password.length > 64)) {
                    showToast('Пароль должен быть от 1 до 64 символов', 'warning');
                    return;
                }
                try {
                    await axios.post('../api/users.php', form.value);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('userModal')).hide();
                    showToast('Пользователь сохранён', 'success');
                    fetchUsers();
                } catch (e) {
                    showToast('Ошибка: ' + (e.response?.data?.error || e.message), 'danger');
                }
            };

            const deleteUser = async (id) => {
                confirmDelete('Удалить пользователя?', 'Пользователь будет удалён.', async () => {
                    try {
                        await axios.delete(`../api/users.php?id=${id}`);
                        showToast('Пользователь удалён', 'success');
                        fetchUsers();
                    } catch (e) {
                        showToast('Ошибка: ' + (e.response?.data?.error || e.message), 'danger');
                    }
                });
            };

            const formatDate = (d) => new Date(d).toLocaleString('ru-RU');

            onMounted(fetchUsers);

            return { users, loading, editingId, form, openModal, saveUser, deleteUser, formatDate };
        }
    }).mount('#app');
</script>

<?php include '../includes/footer.php'; ?>
