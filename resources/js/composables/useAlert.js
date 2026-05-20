import Swal from 'sweetalert2';

const defaultConfig = {
  buttonsStyling: false,
  customClass: {
    confirmButton: 'px-4 py-2 rounded text-sm font-medium transition-colors text-white ml-2',
    cancelButton: 'px-4 py-2 rounded text-sm font-medium transition-colors bg-slate-100 text-slate-700 hover:bg-slate-200',
    popup: 'rounded-xl',
    actions: 'gap-2',
  },
  allowOutsideClick: false,
  allowEscapeKey: true,
};

export function useAlert() {
  const confirm = async (
    title,
    text = 'Essa ação não pode ser desfeita.',
    confirmButtonText = 'Sim, excluir',
    confirmButtonColor = 'red'
  ) => {
    const result = await Swal.fire({
      ...defaultConfig,
      title,
      text,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText,
      cancelButtonText: 'Cancelar',
      confirmButtonColor: confirmButtonColor === 'red' ? '#dc2626' : '#2563eb',
      cancelButtonColor: '#64748b',
      reverseButtons: true,
      focusCancel: true,
    });

    return result.isConfirmed;
  };

  const success = async (title, text = '') => {
    await Swal.fire({
      ...defaultConfig,
      title,
      text,
      icon: 'success',
      confirmButtonText: 'OK',
      confirmButtonColor: '#2563eb',
    });
  };

  const error = async (title, text = '') => {
    await Swal.fire({
      ...defaultConfig,
      title,
      text,
      icon: 'error',
      confirmButtonText: 'OK',
      confirmButtonColor: '#dc2626',
    });
  };

  const info = async (title, text = '') => {
    await Swal.fire({
      ...defaultConfig,
      title,
      text,
      icon: 'info',
      confirmButtonText: 'OK',
      confirmButtonColor: '#2563eb',
    });
  };

  return {
    confirm,
    success,
    error,
    info,
  };
}
