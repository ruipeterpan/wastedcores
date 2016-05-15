#include <linux/module.h> /* Needed by all modules */
#include <linux/kernel.h> /* Needed for KERN_INFO */
#include <linux/moduleparam.h>
#include <linux/sched.h>
#include <linux/device.h>
#include <linux/seq_file.h>
#include <linux/proc_fs.h>
#include <linux/cpumask.h>
#include <linux/interrupt.h>
#include <linux/hrtimer.h>
#include <linux/slab.h>

/*
 * Invariant module checker.
 *
 * Logic:
 * - Every x ms, a tasklet timer is executed (check_balancing_module).
 * - This timer executes sanity checks of the kernel (in kernel/sched/fair.c)
 * - These checks return an array of status for the CPUS. For each CPU: NOT_BUGGY, MAYBE_BUGGY, RESET_BUGGINESS, BUGGY.
 * - A CPU has to be MAYBE_BUGGY multiple times to become BUGGY. This is done using a bit of enum magic (status++ until status == BUGGY)
 * - RESET_BUGGINESS is used when a CPU was buggy, fixed its problem and is buggy again. It resets the status.
 *
 * - When a CPU is in BUGGY state, a bug report is generated (generate_bug_report).
 * - To get the reports: cat /proc/stap_cntl
 */

#define NR_INV 3

static ktime_t kt_period;
static struct tasklet_hrtimer htimer;
static buggy_state_t cpu_status[NR_INV][NR_CPUS];

void generate_bug_report_module(int cpu) {
   static int bug_report_generated = 0;
   if(bug_report_generated)
      return;
   bug_report_generated = 1;

   generate_bug_report(cpu);
}

void buggy_cpu_found(int cpu, int invariant) {
   printk("%d seems to be buggy due to invariant %d!\n", cpu, invariant);
   generate_bug_report_module(cpu);
}

void change_cpu_status(int cpu, int invariant, buggy_state_t status) {
   cpu_status[invariant][cpu] = status;
   if(status == BUGGY)
      buggy_cpu_found(cpu, invariant);
}

void change_cpus_status(int invariant, buggy_state_t *status) {
   int cpu;
   for_each_online_cpu(cpu) {
      if(status[cpu] == NOT_BUGGY && cpu_status[invariant][cpu] != NOT_BUGGY)
         change_cpu_status(cpu, invariant, NOT_BUGGY);
      else if(status[cpu] == MAYBE_BUGGY)
         change_cpu_status(cpu, invariant, cpu_status[invariant][cpu] + 1);
      else if(status[cpu] == RESET_BUGGINESS)
         change_cpu_status(cpu, invariant, MAYBE_BUGGY);
      else if(status[cpu] == BUGGY && cpu_status[invariant][cpu] != BUGGY)
         change_cpu_status(cpu, invariant, BUGGY);
   }
}

void check_balancing_module(void) {
   static int calls;
   u64 start, stop;
   buggy_state_t status[NR_CPUS];

   calls++;


   // Check invariant 1: no idle cpu while another CPU is overloaded
   rdtscll(start);
   memset(status, 0, sizeof(status));
   check_idle_overloaded(status);
   rdtscll(stop);
   change_cpus_status(0, status);
   printk("Invariant 1: %llu cycles\n", (long long unsigned)(stop - start));

   // check invariant 2: no deficit. Do that only every 500 calls!
   /*if(calls % 60 == 0) {
      rdtscll(start);
      memset(status, 0, sizeof(status));
      check_deficit(status);
      rdtscll(stop);
      change_cpus_status(1, status);
      printk("Invariant 2: %llu cycles\n", (long long unsigned)(stop - start));
   }*/

   // check invariant 3: no useless migration
   rdtscll(start);
   memset(status, 0, sizeof(status));
   check_useless_migrations(status);
   rdtscll(stop);
   printk("Invariant 3: %llu cycles\n", (long long unsigned)(stop - start));
   change_cpus_status(2, status);
}

static enum hrtimer_restart timer_function(struct hrtimer * timer) {
   check_balancing_module();
   hrtimer_forward_now(timer, kt_period);
   return HRTIMER_RESTART;
}

static void timer_init(void) {
   //kt_period = ktime_set(0, 10000000); //seconds,nanoseconds - 10ms
   kt_period = ktime_set(1, 0); //seconds,nanoseconds - 1s
   tasklet_hrtimer_init (&htimer, timer_function, CLOCK_REALTIME, HRTIMER_MODE_REL);
   tasklet_hrtimer_start(&htimer, kt_period, HRTIMER_MODE_REL);
}

static void timer_cleanup(void) {
   tasklet_hrtimer_cancel(&htimer);
}

static ssize_t stap_proc_write(struct file *file, const char __user *buf,
      size_t count, loff_t *ppos) {
   int cpu, enabled;
   if (count) {
      int ret = sscanf(buf, "C\t%d\t%d\n", &cpu, &enabled);
      if(ret != 2) {
         printk("Error \"%*.*s\" unrecognized command\n", (int)count, (int)count, buf);
      } else {
         change_cpu_status(cpu, 0, enabled);
      }
   }
   return count;
}

static void *my_seq_start(struct seq_file *s, loff_t *pos) {
   static unsigned long iteration = 0;
   if (*pos == 0) {
      return &iteration;
   } else {
      *pos = 0;
      return NULL;
   }
}

static void *my_seq_next(struct seq_file *s, void *v, loff_t *pos) {
   unsigned long *iteration = (unsigned long *)v;
   (*iteration)++;
   (*pos)++;
   return NULL;
}

static void my_seq_stop(struct seq_file *s, void *v) {
}

static void stap_do_work(struct seq_file *m) {
   int cpu;
   int sd;
   struct sched_report **r = get_reports();
   if(!r)
      return;

   for_each_online_cpu(cpu) {
      for(sd = 0; sd < NR_SCHED_DOMAINS; sd++) {
         if(!r[cpu][sd].rdt)
            continue;
         //printk("CPU %d SD %d\n", cpu, sd);
         seq_printf(m, "Sched report for CPU %d rdt %lu name %s\n", r[cpu][sd].cpu, r[cpu][sd].rdt, r[cpu][sd].sched_domain_name);
         seq_printf(m, r[cpu][sd].bug_report);
      }
   }
}

static int my_seq_show(struct seq_file *s, void *v) {
   stap_do_work(s);
   return 0;
}

static struct seq_operations my_seq_ops = {
   .start = my_seq_start,
   .next  = my_seq_next,
   .stop  = my_seq_stop,
   .show  = my_seq_show
};

static int stap_open(struct inode *inode, struct file *file) {
   return seq_open(file, &my_seq_ops);
}

static int stap_release(struct inode *inode, struct file *file) {
   return seq_release(inode, file);
}

static const struct file_operations stap_cntrl_fops = {
   .write  = stap_proc_write,
   .open           = stap_open,
   .read           = seq_read,
   .llseek         = seq_lseek,
   .release        = stap_release,
};

void stap_create_procs_files(void) {
   proc_create("stap_cntl", S_IRWXUGO, NULL, &stap_cntrl_fops);
}

void stap_remove_proc_files(void) {
   remove_proc_entry("stap_cntl", NULL);
}

void set_only_use_rq_0(int val);
static int __init stap_init_module(void) {
   //set_only_use_rq_0(1);
   //set_invariant_debug(1000);
   stap_create_procs_files();
   timer_init();
   return 0;
}

static void __exit stap_exit_module(void) {
   //set_only_use_rq_0(0);
   //set_invariant_debug(0);
   timer_cleanup();
   stap_remove_proc_files();
}

module_init(stap_init_module);
module_exit(stap_exit_module);

MODULE_LICENSE("GPL");
MODULE_AUTHOR("Baptiste Lepers");
MODULE_DESCRIPTION("Control stap scripts");
