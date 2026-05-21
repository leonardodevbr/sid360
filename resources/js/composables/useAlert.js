import Swal from 'sweetalert2';

export const swalDefaultConfig = {
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

const dangerConfirmClass = `${swalDefaultConfig.customClass.confirmButton} swal2-confirm--danger`;

export function useAlert() {
  const confirm = async (
    title,
    text = 'Essa ação não pode ser desfeita.',
    confirmButtonText = 'Sim, excluir',
  ) => {
    const result = await Swal.fire({
      ...swalDefaultConfig,
      customClass: {
        ...swalDefaultConfig.customClass,
        confirmButton: dangerConfirmClass,
      },
      title,
      text,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText,
      cancelButtonText: 'Cancelar',
      reverseButtons: true,
      focusCancel: true,
    });

    return result.isConfirmed;
  };

  const success = async (title, text = '') => {
    await Swal.fire({
      ...swalDefaultConfig,
      title,
      text,
      icon: 'success',
      confirmButtonText: 'OK',
    });
  };

  const error = async (title, text = '') => {
    await Swal.fire({
      ...swalDefaultConfig,
      title,
      text,
      icon: 'error',
      confirmButtonText: 'OK',
    });
  };

  const info = async (title, text = '') => {
    await Swal.fire({
      ...swalDefaultConfig,
      title,
      text,
      icon: 'info',
      confirmButtonText: 'OK',
    });
  };

  return {
    confirm,
    success,
    error,
    info,
  };
}
